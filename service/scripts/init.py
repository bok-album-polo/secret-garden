#!/usr/bin/env python3
"""
Initialization script for the Secret Garden application.

This script:
1. Validates YAML configuration files against the expected schema.
2. Clones public-site-source folder

Usage:
    python init.py [filename]
    
    filename: The YAML file to validate (default: service/init.yaml)
"""

import random
import sys
import os
import shutil
import re
from pathlib import Path
from math import ceil
import yaml
import json
from typing import Any, Dict, List, Optional
from jsonschema import validate, ValidationError, Draft7Validator

# Global repository root and build directory (set by `main()`)
REPO_ROOT: Optional[Path] = None
BUILD_DIR: Optional[Path] = None


# Define the schema for the configuration file
CONFIG_SCHEMA = {
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "object",
    "required": [
        "project_meta",
        "database_server",
        "application_config",
        "secret_door_fields",
        "secret_page_fields",
        "admin_site",
        "public_sites"
    ],
    "properties": {
        "project_meta": {
            "type": "object",
            "required": ["version", "environment", "num_public_sites", "num_unique_pk_sequences", "mode"],
            "properties": {
                "version": {"type": "string"},
                "environment": {"type": "string", "enum": ["production", "development"]},
                "num_public_sites": {"type": "integer", "minimum": 1},
                "num_unique_pk_sequences": {"type": "integer", "minimum": 1},
                "mode": {"type": "string", "enum": ["readonly", "readwrite"]}
            },
            "additionalProperties": False
        },
        "database_server": {
            "type": "object",
            "required": ["host", "port", "db_name"],
            "properties": {
                "host": {"type": "string"},
                "port": {"type": "integer", "minimum": 1, "maximum": 65535},
                "db_name": {"type": "string"}
            },
            "additionalProperties": False
        },
        "application_config": {
            "type": "object",
            "required": [
                "pk_length",
                "pk_max_history",
                "generated_password_length",
                "generated_password_charset"
            ],
            "properties": {
                "pk_length": {"type": "integer", "minimum": 1},
                "pk_max_history": {"type": "integer", "minimum": 1},
                "generated_password_length": {"type": "integer", "minimum": 1},
                "generated_password_charset": {"type": "string"}
            },
            "additionalProperties": False
        },
        "secret_door_fields": {
            "type": "array",
            "items": {"$ref": "#/definitions/field"}
        },
        "secret_page_fields": {
            "type": "array",
            "items": {"$ref": "#/definitions/field"}
        },
        "admin_site": {
            "type": "object",
            "required": ["domain", "db_credentials"],
            "properties": {
                "domain": {"type": "string"},
                "db_credentials": {"$ref": "#/definitions/db_credentials"}
            },
            "additionalProperties": False
        },
        "public_sites": {
            "type": "array",
            "minItems": 1,
            "items": {
                "type": "object",
                "required": ["domain", "db_credentials", "routing_secrets", "pages_menu"],
                "properties": {
                    "domain": {"type": "string"},
                    "db_credentials": {"$ref": "#/definitions/db_credentials"},
                    "routing_secrets": {
                        "type": "object",
                        "required": ["secret_door", "secret_page"],
                        "properties": {
                            "secret_door": {"type": "string"},
                            "secret_page": {"type": "string"}
                        },
                        "additionalProperties": False
                    },
                    "pages_menu": {
                        "type": "array",
                        "items": {"type": "string"},
                        "minItems": 1
                    },
                    "num_tripwire_pages": {"type": "integer", "minimum": 0},
                    "secret_door_fields": {
                        "type": "array",
                        "items": {"$ref": "#/definitions/domain_field"}
                    }
                },
                "additionalProperties": False
            }
        }
    },
    "additionalProperties": False,
    "definitions": {
        "db_credentials": {
            "type": "object",
            "required": ["user", "pass"],
            "properties": {
                "user": {"type": "string"},
                "pass": {"type": "string"}
            },
            "additionalProperties": False
        },
        "field": {
            "type": "object",
            "required": ["name", "label", "html_type", "pg_type", "required"],
            "properties": {
                "name": {"type": "string"},
                "label": {"type": "string"},
                "html_type": {"type": "string"},
                "help_text": {"type": "string"},
                "maxlength": {"type": "integer", "minimum": 1},
                "pg_type": {"type": "string"},
                "required": {"type": "boolean"},
                "options": {
                    "type": "array",
                    "items": {"type": "string"}
                }
            },
            "additionalProperties": False
        },
        "domain_field": {
            "type": "object",
            "required": ["name", "label", "help_text"],
            "properties": {
                "name": {"type": "string"},
                "label": {"type": "string"},
                "help_text": {"type": "string"},
                "html_type": {"type": "string"},
                "maxlength": {"type": "integer", "minimum": 1},
                "pg_type": {"type": "string"},
                "required": {"type": "boolean"},
                "options": {
                    "type": "array",
                    "items": {"type": "string"}
                }
            },
            "additionalProperties": False
        }
    }
}


# `get_service_path` removed: functions now compute `repo_root` from __file__ directly.


def load_yaml_file(filepath: Path) -> Dict[str, Any]:
    """Load and parse a YAML file."""
    try:
        with open(filepath, 'r') as f:
            data = yaml.safe_load(f)
        if data is None:
            raise ValueError("YAML file is empty")
        return data
    except FileNotFoundError:
        raise FileNotFoundError(f"File not found: {filepath}")
    except yaml.YAMLError as e:
        raise ValueError(f"Invalid YAML syntax: {e}")


def validate_config(config: Dict[str, Any], schema: Dict[str, Any]) -> tuple[bool, Optional[str]]:
    """
    Validate configuration against the schema.
    
    Returns:
        tuple: (is_valid, error_message)
    """
    try:
        validate(instance=config, schema=schema)
        return True, None
    except ValidationError as e:
        return False, str(e)


def validate_cross_references(config: Dict[str, Any]) -> tuple[bool, Optional[str]]:
    """
    Validate cross-references and business logic constraints.
    
    Returns:
        tuple: (is_valid, error_message)
    """
    errors = []
    
    # Check that num_public_sites matches the number of public_sites
    num_expected = config.get("project_meta", {}).get("num_public_sites", 0)
    num_actual = len(config.get("public_sites", []))
    if num_expected != num_actual:
        errors.append(
            f"project_meta.num_public_sites ({num_expected}) does not match "
            f"the number of public_sites configured ({num_actual})"
        )
    
    # Check that pk_max_history >= pk_length
    app_config = config.get("application_config", {})
    pk_length = app_config.get("pk_length", 0)
    pk_max_history = app_config.get("pk_max_history", 0)
    if pk_max_history < pk_length:
        errors.append(
            f"application_config.pk_max_history ({pk_max_history}) must be >= "
            f"application_config.pk_length ({pk_length})"
        )
    
    # Validate unique domains
    all_domains = [config.get("admin_site", {}).get("domain")]
    public_domains = [site.get("domain") for site in config.get("public_sites", [])]
    all_domains.extend(public_domains)
    
    seen = set()
    for domain in all_domains:
        if domain in seen:
            errors.append(f"Duplicate domain found: {domain}")
        seen.add(domain)

    # Validate num_tripwire_pages per public site: must be < (pages_menu count - 2)
    for site in config.get("public_sites", []):
        domain = site.get("domain", "unknown")
        pages_menu_len = len(site.get("pages_menu", []))
        num_tripwire = site.get("num_tripwire_pages")
        if num_tripwire is None:
            continue
        if not isinstance(num_tripwire, int):
            errors.append(f"{domain}: num_tripwire_pages must be an integer")
            continue
        threshold = pages_menu_len - 2
        if num_tripwire >= threshold:
            errors.append(
                f"{domain}: num_tripwire_pages ({num_tripwire}) must be < (pages_menu count - 2) ({threshold})"
            )
    
    if errors:
        return False, "\n".join(errors)
    return True, None


def compute_discovery_probabilities(num_pages_menu: int,
                                    num_sequences_per_site: int,
                                    pk_length: int,
                                    sliding_window: int,
                                    num_tripwire_pages: int = 0) -> tuple[float, float, list]:
    """Compute discovery probabilities for a site.

    Returns a tuple of (P_session_total, P_single, steps)
    where `steps` is a list of (step, P_survival, P_step).
    """
    # Guard against invalid menu sizes
    if num_pages_menu <= 1:
        return 0.0, 0.0, []

    p_single = num_sequences_per_site / ((num_pages_menu - 1) ** pk_length)
    p_session = 0.0
    steps = []
    for step in range(1, sliding_window + 1):
        # base survival probability per step (pages that are not tripwires)
        denom = (num_pages_menu - 1)
        base_numer = (num_pages_menu - 1 - (num_tripwire_pages or 0))
        base = base_numer / denom if denom > 0 else 0
        if base <= 0:
            p_survival = 0.0
        else:
            p_survival = base ** (step + pk_length - 1)
        p_step = p_single * p_survival
        steps.append((step, p_survival, p_step))
        p_session += p_step

    return p_session, p_single, steps


def clear_generated_content() -> None:
    """Remove the `build` directory (where all generated content is stored).

    Uses the module-level `BUILD_DIR` or `REPO_ROOT` if set, otherwise falls back to the current working directory.
    """
    global BUILD_DIR, REPO_ROOT
    if BUILD_DIR is not None:
        build_dir = BUILD_DIR
    elif REPO_ROOT is not None:
        build_dir = REPO_ROOT / 'build'
    else:
        build_dir = Path.cwd() / 'build'

    if build_dir.exists():
        try:
            if build_dir.is_dir():
                shutil.rmtree(build_dir)
            else:
                build_dir.unlink()
            print(f"Removed generated build directory: {build_dir}")
        except Exception as e:
            print(f"Warning: failed to remove {build_dir}: {e}")
    else:
        print("No generated build directory found to remove.")


def clone_public_sites(config: Dict[str, Any], source_dir: Optional[Path] = None) -> None:
    """Clone `public-site-source` into numbered directories under the module `BUILD_DIR` or `REPO_ROOT`."""
    # Determine repo_root and source_dir
    global REPO_ROOT, BUILD_DIR
    repo_root = REPO_ROOT if REPO_ROOT is not None else (BUILD_DIR.parent if BUILD_DIR is not None else Path(__file__).resolve().parents[2])
    if source_dir is None:
        source_dir = repo_root / "public-site-source"

    print("\nCloning public-site-source for each public site:")
    if not source_dir.exists() or not source_dir.is_dir():
        print(f"Warning: source directory not found: {source_dir}")
        return

    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    build_dir.mkdir(parents=True, exist_ok=True)

    for idx, site in enumerate(config.get("public_sites", []), start=1):
        domain = site.get("domain", f"site{idx}")
        # sanitize domain to a filesystem-friendly directory name
        safe = re.sub(r'[^A-Za-z0-9._-]+', '-', domain)
        safe = safe.strip('-').lower()
        dest = build_dir / f"{idx:02d}-{safe}"
        try:
            if dest.exists():
                print(f"  Removing existing destination: {dest}")
                if dest.is_dir():
                    shutil.rmtree(dest)
                else:
                    dest.unlink()
            shutil.copytree(source_dir, dest)
            print(f"  Copied {source_dir} -> {dest}")
        except Exception as e:
            print(f"  Error copying to {dest}: {e}")


def clone_admin_site() -> Optional[Path]:
    """Clone `admin-site-source` into `build/admin-site` and return the destination path or None on failure."""
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')

    source_dir = repo_root / 'admin-site-source'
    if not source_dir.exists() or not source_dir.is_dir():
        print(f"Warning: admin source directory not found: {source_dir}")
        return None

    print("\nCloning admin-site-source:")
    dest = build_dir / "admin-site"
    try:
        if dest.exists():
            print(f"  Removing existing admin destination: {dest}")
            if dest.is_dir():
                shutil.rmtree(dest)
            else:
                dest.unlink()
        shutil.copytree(source_dir, dest)
        print(f"  Copied {source_dir} -> {dest}")
        return dest
    except Exception as e:
        print(f"  Error copying admin source to {dest}: {e}")
        return None


def generate_public_config_php(config: Dict[str, Any]) -> None:
    """Generate a `config/config.php` file for each generated public site.

    The PHP file will set `$config` to the decoded JSON for that domain's configuration.
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else (BUILD_DIR.parent if BUILD_DIR is not None else Path(__file__).resolve().parents[2])
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')

    for idx, site in enumerate(config.get("public_sites", []), start=1):
        domain = site.get("domain", f"site{idx}")
        safe = re.sub(r'[^A-Za-z0-9._-]+', '-', domain).strip('-').lower()
        site_dir = build_dir / f"{idx:02d}-{safe}"
        if not site_dir.exists():
            print(f"Warning: generated site directory not found for {domain}: {site_dir}")
            continue

        cfg_dir = site_dir / 'config'
        cfg_dir.mkdir(parents=True, exist_ok=True)
        cfg_path = cfg_dir / 'config.php'

        # Prepare JSON payload: merge site config with selected project_meta and application_config fields
        payload = dict(site)  # shallow copy of the site-specific config

        # Remove num_tripwire_pages from export (we'll generate tripwire_pages instead)
        num_tripwire = payload.pop('num_tripwire_pages', 0) or 0

        # Add selected project_meta fields
        project_meta = config.get('project_meta', {})
        payload['project_meta'] = {
            'environment': project_meta.get('environment'),
            'mode': project_meta.get('mode')
        }

        # Add selected application_config fields
        app_cfg = config.get('application_config', {})
        payload['application_config'] = {
            'pk_length': app_cfg.get('pk_length'),
            'pk_max_history': app_cfg.get('pk_max_history'),
            'generated_password_length': app_cfg.get('generated_password_length'),
            'generated_password_charset': app_cfg.get('generated_password_charset')
        }

        # Generate tripwire_pages: random picks from pages_menu excluding the first page and the secret_door value
        pages_menu = site.get('pages_menu', []) or []
        candidates = pages_menu[1:] if len(pages_menu) > 1 else []
        secret_door = site.get('routing_secrets', {}).get('secret_door') if site.get('routing_secrets') else None
        if secret_door in candidates:
            candidates.remove(secret_door)

        tripwire = []
        try:
            num_tripwire_int = int(num_tripwire)
        except Exception:
            num_tripwire_int = 0

        if num_tripwire_int > 0 and candidates:
            count = min(num_tripwire_int, len(candidates))
            sysrand = random.SystemRandom()
            tripwire = sysrand.sample(candidates, count)

        payload['tripwire_pages'] = tripwire

        # Merge root-level secret_door_fields into the site's secret_door_fields.
        # - Fill missing attributes (html_type, maxlength, etc.) from root by matching `name`.
        # - Append any root fields missing entirely from the site so each site has the full set.
        root_fields = {f['name']: f for f in config.get('secret_door_fields', [])}
        site_fields = payload.get('secret_door_fields', []) or []

        merged_fields = []
        seen = set()
        for s in site_fields:
            name = s.get('name')
            if not name:
                merged_fields.append(dict(s))
                continue
            root = root_fields.get(name)
            merged = dict(s)  # copy site-specific values
            if root:
                # Copy useful keys from root only when missing in site field
                for key in ('html_type', 'maxlength', 'required', 'options'):
                    if key in root and key not in merged:
                        merged[key] = root[key]
            merged_fields.append(merged)
            seen.add(name)

        # Append root fields not present in the site, but drop 'pg_type' (site uses domain-specific types)
        for name, root in root_fields.items():
            if name in seen:
                continue
            appended = dict(root)
            appended.pop('pg_type', None)
            merged_fields.append(appended)

        payload['secret_door_fields'] = merged_fields

        # Include root-level secret_page_fields in the per-site config, but drop 'pg_type'
        root_page_fields = config.get('secret_page_fields', []) or []
        processed_page_fields = []
        for pf in root_page_fields:
            pcopy = dict(pf)
            pcopy.pop('pg_type', None)
            processed_page_fields.append(pcopy)
        payload['secret_page_fields'] = processed_page_fields

        json_payload = json.dumps(payload, indent=2, ensure_ascii=False)

        php_content = ("<?php\n"
                       "// Generated configuration for domain: %s\n"
                       "$config = json_decode(<<<'JSON'\n%s\nJSON\n, true);\n") % (domain, json_payload)

        try:
            with open(cfg_path, 'w', encoding='utf-8') as f:
                f.write(php_content)
            print(f"  Wrote config for {domain} -> {cfg_path}")
        except Exception as e:
            print(f"  Error writing config for {domain} at {cfg_path}: {e}")


def generate_admin_config_php(config: Dict[str, Any]) -> None:
    """Clone `admin-site-source` into `build` and generate its `config/config.php` file."""
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else (BUILD_DIR.parent if BUILD_DIR is not None else Path(__file__).resolve().parents[2])
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')

    admin = config.get('admin_site', {})

    cfg_dir = build_dir / 'admin-site' / 'config'
    cfg_dir.mkdir(parents=True, exist_ok=True)
    cfg_path = cfg_dir / 'config.php'

    # Prepare payload for admin site (do not include 'domain' field)
    payload = dict(admin)
    payload.pop('domain', None)

    # Add selected project_meta fields
    project_meta = config.get('project_meta', {})
    payload['project_meta'] = {
        'environment': project_meta.get('environment'),
        'mode': project_meta.get('mode')
    }

    # Add selected application_config fields (exclude pk_length and pk_max_history)
    app_cfg = config.get('application_config', {})
    payload['application_config'] = {
        'generated_password_length': app_cfg.get('generated_password_length'),
        'generated_password_charset': app_cfg.get('generated_password_charset')
    }

    # Include root-level secret_door_fields (drop pg_type)
    root_door_fields = config.get('secret_door_fields', []) or []
    processed_door_fields = []
    for df in root_door_fields:
        dcopy = dict(df)
        dcopy.pop('pg_type', None)
        processed_door_fields.append(dcopy)
    payload['secret_door_fields'] = processed_door_fields

    # Include root-level secret_page_fields (drop pg_type)
    root_page_fields = config.get('secret_page_fields', []) or []
    processed_page_fields = []
    for pf in root_page_fields:
        pcopy = dict(pf)
        pcopy.pop('pg_type', None)
        processed_page_fields.append(pcopy)
    payload['secret_page_fields'] = processed_page_fields

    json_payload = json.dumps(payload, indent=2, ensure_ascii=False)

    php_content = ("<?php\n"
                   "// Generated ADMIN configuration\n"
                   "$config = json_decode(<<<'JSON'\n%s\nJSON\n, true);\n") % (json_payload)

    try:
        with open(cfg_path, 'w', encoding='utf-8') as f:
            f.write(php_content)
        print(f"  Wrote admin config -> {cfg_path}")
    except Exception as e:
        print(f"  Error writing admin config at {cfg_path}: {e}")


def main():
    """Main entry point."""
    # Compute repository root (two levels up from this script: /repo_root)
    repo_root = Path(__file__).resolve().parents[2]
    service_dir = repo_root / 'service'
    global REPO_ROOT, BUILD_DIR
    REPO_ROOT = repo_root
    BUILD_DIR = repo_root / "build"

    # Handle --reset flag
    if '--reset' in sys.argv:
        clear_generated_content()
        print("Reset complete.")
        return 0

    if len(sys.argv) > 1:
        # Use provided filename as-is (skip --reset if present)
        filenames = [arg for arg in sys.argv[1:] if not arg.startswith('--')]
        filepath = Path(filenames[0]) if filenames else (service_dir / "init.yaml")
    else:
        # Default to service/init.yaml
        filepath = service_dir / "init.yaml"

    print(f"Validating configuration file: {filepath}")

    # Check for existing generated content in `build` and prompt user about clearing
    if BUILD_DIR.exists():
        ans = input("Generated content found in 'build'. Clear all generated content and start over? [y/N]: ").strip().lower()
        if ans in ("y", "yes"):
            clear_generated_content()
            print("Starting over after cleanup.")
        else:
            print("Initialization aborted by user.")
            return 0

    try:
        # Load YAML file
        config = load_yaml_file(filepath)
        print("✓ YAML file loaded successfully")

        # Validate against schema
        is_valid, error_msg = validate_config(config, CONFIG_SCHEMA)
        if not is_valid:
            print(f"✗ Schema validation failed:")
            print(f"  {error_msg}")
            return 1
        print("✓ Schema validation passed")

        # Validate cross-references
        is_valid, error_msg = validate_cross_references(config)
        if not is_valid:
            print(f"✗ Cross-reference validation failed:")
            for line in error_msg.split("\n"):
                print(f"  {line}")
            return 1
        print("✓ Cross-reference validation passed")
        print("✓ Configuration is valid!")

        # Compute and display discovery probability for each public site
        print("\nPK Discovery Probability Analysis:")
        app_config = config.get("application_config", {})
        pk_length = app_config.get("pk_length", 0)
        pk_max_history = app_config.get("pk_max_history", 0)
        sliding_window = pk_max_history - pk_length + 1

        num_unique_pk_sequences = config.get("project_meta", {}).get("num_unique_pk_sequences", 0)
        num_public_sites = len(config.get("public_sites", []))
        num_sequences_per_site = ceil(num_unique_pk_sequences / num_public_sites)

        for site in config.get("public_sites", []):
            domain = site.get("domain", "unknown")
            num_pages_menu = len(site.get("pages_menu", []))

            num_tripwire = site.get("num_tripwire_pages", 0)
            p_session, p_single, steps = compute_discovery_probabilities(
                num_pages_menu, num_sequences_per_site, pk_length, sliding_window, num_tripwire)

            print(f"  {domain}:")
            print(f"    num_pages={num_pages_menu}, num_sequences_per_site={num_sequences_per_site}, num_tripwire={num_tripwire}")
            print(f"    P_single={num_sequences_per_site}/(({num_pages_menu}-1)^{pk_length})={p_single}")
            print(f"    sliding_window={sliding_window}")
            print(f"    P_session={p_session:.8f}")

        source_dir = repo_root / "public-site-source"
        clone_public_sites(config, source_dir)

        # Generate per-site `config/config.php` files for public sites
        generate_public_config_php(config)

        # Clone admin site into build and then generate its `config/config.php`
        clone_admin_site()
        generate_admin_config_php(config)

        # Report where generated sites were placed
        try:
            print(f"✓ Generated sites available under: {repo_root / 'build'}")
        except Exception:
            print("✓ Generated sites created (build location may vary)")

        return 0
        
    except (FileNotFoundError, ValueError) as e:
        print(f"✗ Error: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
