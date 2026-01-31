#!/usr/bin/env python3
"""
Initialization script for the Secret Garden application.

This script:
1. Validates YAML configuration files against the expected schema.
2. Clones public-site-source and admin-site-source folder
3. Generates config.php for public sites and admin site
4. Generates SQL for site setup

Usage:
    python init.py [filename]
    
    filename: The YAML file to validate (default: service/init.yaml)
"""

import csv
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
import urllib.request

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
                "num_generated_usernames": {"type": "integer", "minimum": 10},
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
                "generated_password_charset": {"type": "string"},
                "common_sequence_threshold": {"type": "number", "minimum": 0.0, "maximum": 1.0}
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

        pages_menu = site.get("pages_menu", [])
        routing = site.get("routing_secrets", {})
        secret_door = routing.get("secret_door")
        secret_page = routing.get("secret_page")

        # 1. Secret Door MUST be in the menu (it is the visible entry point)
        if secret_door not in pages_menu:
            errors.append(
                f"{domain}: secret_door '{secret_door}' is NOT in pages_menu. "
                "The entry point must be an existing page."
            )

        # 2. Secret Page MUST NOT be in the menu (it must remain hidden)
        if secret_page in pages_menu:
            errors.append(
                f"{domain}: secret_page '{secret_page}' IS in pages_menu. "
                "Security Risk: The registration/hidden page should not be linked in the public menu."
            )
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

        site['tripwire_pages'] = tripwire
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

def is_string_in_top_n(file_path: Path, string: str, n_threshold: int) -> bool:
    """
    Checks if the string exists within the top N lines of the common PIN file.
    
    Args:
        file_path: Path to the downloaded common PIN list.
        string: The generated PK sequence string.
        n_threshold: The line number limit (e.g., top 100).
        
    Returns:
        bool: True if found within top N lines, False otherwise.
    """
    if not file_path.exists() or n_threshold <= 0:
        return False
        
    try:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            for line_num, line in enumerate(f, 1):
                if line_num > n_threshold:
                    break
                
                # Clean line: split by comma or space to handle CSVs with metadata
                line_content = line.strip().split(',')[0].split()[0]
                
                if line_content == string:
                    print(f"  ⚠️ String '{string}' found at rank {line_num} (Top {n_threshold} filter).")
                    return True
    except Exception as e:
        print(f"  ⚠️ Error reading file for filtering: {e}")
        
    return False

def generate_pk_sequences(config: Dict[str, Any]) -> None:
    """
    Generates unique PK sequences for each domain and saves them to a CSV.
    
    Constraints:
    - Sequence length is defined by application_config.pk_length.
    - Sequence MUST NOT start with index 0 (home).
    - Sequence CAN contain index 0 in any other position.
    - Sequence MUST NOT contain any indices of tripwire pages.
    - The same digit MUST NOT appear consecutively (e.g., no '22').
    
    Output:
    - build/pk_sequences.csv (Format: domain, pk_sequence).
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else (BUILD_DIR.parent if BUILD_DIR is not None else Path(__file__).resolve().parents[2])
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')

    app_config = config.get("application_config", {})
    pk_length = app_config.get("pk_length", 5)
    common_sequence_threshold = app_config.get("common_sequence_threshold", 0.0)
    common_sequence_threshold = int(pow(10, pk_length) * common_sequence_threshold)

    has_common_pin_file = False

    # --- BEGIN COMMON PIN DOWNLOAD BLOCK ---
    common_pin_urls = {
        3: "https://github.com/Slon104/Common-PIN-Analysis-from-haveibeenpwned.com/raw/refs/heads/main/Word%20Lists/5%20PIN%20by%20Slon104.txt",
        4: "https://github.com/Slon104/Common-PIN-Analysis-from-haveibeenpwned.com/raw/refs/heads/main/Word%20Lists/4%20PIN%20by%20Slon104.txt",
        5: "https://github.com/Slon104/Common-PIN-Analysis-from-haveibeenpwned.com/raw/refs/heads/main/Word%20Lists/5%20PIN%20by%20Slon104.txt",
        6: "https://github.com/Slon104/Common-PIN-Analysis-from-haveibeenpwned.com/raw/refs/heads/main/Word%20Lists/6%20PIN%20by%20Slon104.txt",
    }

    if pk_length in common_pin_urls:
        url = common_pin_urls[pk_length]
        common_pin_file = build_dir / f"common_pins_{pk_length}.txt"
        print(f"[*] Downloading Common PIN list for pk_length {pk_length}...")
        try:
            build_dir.mkdir(parents=True, exist_ok=True)
            urllib.request.urlretrieve(url, common_pin_file)
            has_common_pin_file = True
            print(f"✓ Common PIN list saved to: {common_pin_file}")
        except Exception as e:
            print(f"⚠️ Failed to download PIN list: {e}. Continuing without filter.")
    else:
        print(f"[*] No Common PIN list found for pk_length {pk_length}, continuing.")
    # --- END COMMON PIN DOWNLOAD BLOCK ---

    public_sites = config.get("public_sites", [])
    if not public_sites:
        return

    num_sequences_per_site = app_config.get('num_sequences_per_site', 10)
    all_generated_pairs = []
    sysrand = random.SystemRandom()

    print(f"\nGenerating PK Sequences (Length: {pk_length}):")

    for site in public_sites:
        domain = site.get("domain", "unknown")
        pages_menu = site.get("pages_menu", [])
        
        # Access tripwires stored in the config datastore by generate_public_config_php
        tripwire_names = site.get('tripwire_pages', [])
        tripwire_indices = {pages_menu.index(t) for t in tripwire_names if t in pages_menu}
        
        # 1. Define all valid indices (including 0/home, excluding tripwires)
        valid_indices = [i for i in range(len(pages_menu)) if i not in tripwire_indices]
        
        # 2. Define indices valid for the START of the sequence (exclude 0)
        valid_start_indices = [i for i in valid_indices if i != 0]

        if not valid_start_indices:
            print(f"  ⚠️ Warning: No valid start indices for {domain}.")
            continue

        # 3. Generate Unique Sequences
        site_sequences = set()
        attempts = 0
        while len(site_sequences) < num_sequences_per_site and attempts < 5000:
            seq_digits = []
            digit = last_digit = None
            
            for position in range(pk_length):
                # Constraint: No consecutive identical digits
                while digit == last_digit:
                    if position == 0:
                        digit = sysrand.choice(valid_start_indices)
                    else:
                        digit = sysrand.choice(valid_indices)

                seq_digits.append(str(digit))
                last_digit = digit
            
            if len(seq_digits) >= pk_length:
                candidate_seq = "".join(seq_digits)
                if has_common_pin_file:
                    if is_string_in_top_n(common_pin_file, candidate_seq, common_sequence_threshold):
                        attempts += 1
                        continue  # Discard and try again
                site_sequences.add(candidate_seq)
            attempts += 1

        for s in site_sequences:
            all_generated_pairs.append((domain, s))
        
        print(f"  ✓ Generated {len(site_sequences)} sequences for {domain}")

    # 4. Write to CSV
    csv_path = build_dir / "pk_sequences.csv"
    try:
        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerows(all_generated_pairs)
        print(f"✓ PK sequences successfully exported to: {csv_path}")
    except Exception as e:
        print(f"  ✗ Error writing PK sequences CSV: {e}")

def generate_sql_01_roles(config: Dict[str, Any]) -> None:
    """
    Generates 01_roles.sql in build/database.
    Defines database roles for ALL sites (Admin + Public) uniformly.
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    db_dir = build_dir / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    sql_path = db_dir / '01_roles.sql'

    lines = ["-- Generated roles for Secret Garden"]
    processed_users = set()

    # Combine Admin site and Public sites into a single list to process uniformly
    all_sites = [config.get('admin_site', {})] + config.get('public_sites', [])

    for site in all_sites:
        creds = site.get('db_credentials', {})
        user = creds.get('user')
        password = creds.get('pass')

        # Create role only if credentials exist and user hasn't been created yet
        if user and password and user not in processed_users:
            lines.append(f'CREATE ROLE "{user}" WITH LOGIN PASSWORD \'{password}\';')
            processed_users.add(user)

    sql_content = "\n".join(lines) + "\n"

    try:
        with open(sql_path, 'w', encoding='utf-8') as f:
            f.write(sql_content)
        print(f"✓ SQL script generated: {sql_path}")
    except Exception as e:
        print(f"  ✗ Error writing 01_roles.sql: {e}")

def generate_sql_02_tables_extensions(config: Dict[str, Any]) -> None:
    """
    Generates 02_tables_extensions.sql in build/database.
    Generates dynamic columns from root-level secret_door/room_fields.
    Applies maxlength to VARCHAR fields.
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    db_dir = build_dir / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    out_sql_path = db_dir / '02_tables_extensions.sql'
    
    # # Source path for the base SQL template
    # base_sql_path = repo_root / 'service' / 'database' / 'base-02-tables.sql'

    # if not base_sql_path.exists():
    #     print(f"  ✗ Error: Base SQL file not found at {base_sql_path}")
    #     return

    # try:
    #     with open(base_sql_path, 'r', encoding='utf-8') as f:
    #         base_sql_content = f.read()
    # except Exception as e:
    #     print(f"  ✗ Error reading base SQL: {e}")
    #     return

    extensions = ["\n-- Dynamic Schema Extensions based on YAML Config"]

    # 1. Aggregate Secret Door Fields (Root Only)
    secret_door_fields: Dict[str, str] = {}

    for field in config.get('secret_door_fields', []):
        name = field.get('name')
        if not name:
            continue
            
        pg_type = field.get('pg_type', 'TEXT')
        # If type is VARCHAR, append the length
        if pg_type.upper() == 'VARCHAR':
            length = field.get('maxlength', 255)
            pg_type = f"VARCHAR({length})"
            
        secret_door_fields[name] = pg_type

    if secret_door_fields:
        extensions.append("-- Extending secret_door_submissions")
        for col, dtype in secret_door_fields.items():
            extensions.append(f'ALTER TABLE secret_door_submissions ADD COLUMN IF NOT EXISTS "{col}" {dtype};')

    # 2. Aggregate Secret Room Fields (Global)
    # Variable renamed from room_cols to secret_room_fields
    secret_room_fields: Dict[str, str] = {}
    
    for field in config.get('secret_page_fields', []):
        name = field.get('name')
        if not name:
            continue

        pg_type = field.get('pg_type', 'TEXT')
        # If type is VARCHAR, append the length
        if pg_type.upper() == 'VARCHAR':
            length = field.get('maxlength', 255)
            pg_type = f"VARCHAR({length})"

        secret_room_fields[name] = pg_type

    if secret_room_fields:
        extensions.append("\n-- Extending secret_room_submissions")
        for col, dtype in secret_room_fields.items():
            extensions.append(f'ALTER TABLE secret_room_submissions ADD COLUMN IF NOT EXISTS "{col}" {dtype};')

    # Combine and Write
    full_sql = "".join(extensions) + "\n"

    try:
        with open(out_sql_path, 'w', encoding='utf-8') as f:
            f.write(full_sql)
        print(f"✓ SQL script generated: {out_sql_path}")
    except Exception as e:
        print(f"  ✗ Error writing {out_sql_path}: {e}")

def copy_sql_template(source_name: str, dest_name: str) -> None:
    """
    Generic utility to copy static SQL files from service/database to build/database.
    
    Args:
        source_name: Filename in service/database (e.g. 'base-03-policies.sql')
        dest_name: Output filename in build/database (e.g. '03_policies.sql')
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    db_dir = build_dir / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    
    source_path = repo_root / 'service' / 'database' / source_name
    dest_path = db_dir / dest_name

    if not source_path.exists():
        print(f"  ✗ Error: Source SQL file not found at {source_path}")
        return

    try:
        shutil.copy(source_path, dest_path)
        print(f"✓ SQL script copied: {dest_path}")
    except Exception as e:
        print(f"  ✗ Error copying {dest_name}: {e}")

def generate_sql_05_permissions(config: Dict[str, Any]) -> None:
    """
    Generates 05_permissions.sql in build/database.
    Reads base-05-permissions.sql and generates a block of permissions 
    for EACH unique database user found in the config (admin + public sites).
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    db_dir = build_dir / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    out_sql_path = db_dir / '05_permissions.sql'
    
    # Source path for the base SQL template
    base_sql_path = repo_root / 'service' / 'database' / 'base-05-permissions.sql'

    if not base_sql_path.exists():
        print(f"  ✗ Error: Base SQL file not found at {base_sql_path}")
        return

    try:
        with open(base_sql_path, 'r', encoding='utf-8') as f:
            base_template = f.read()
    except Exception as e:
        print(f"  ✗ Error reading base permissions SQL: {e}")
        return

    final_sql_blocks = ["-- Generated Permissions for Secret Garden Users"]
    processed_users = set()

    # Collect all unique users from Admin and Public sites
    all_sites = [config.get('admin_site', {})] + config.get('public_sites', [])

    for site in all_sites:
        creds = site.get('db_credentials', {})
        user = creds.get('user')
        
        if user and user not in processed_users:
            # Replace placeholder 'dbuser' with the actual username
            # We use distinct SQL blocks for each user to ensure full coverage
            user_block = base_template.replace("dbuser", f'"{user}"')
            final_sql_blocks.append(f"\n-- Permissions for role: {user}")
            final_sql_blocks.append(user_block)
            processed_users.add(user)

    try:
        with open(out_sql_path, 'w', encoding='utf-8') as f:
            f.write("\n".join(final_sql_blocks))
        print(f"✓ SQL script generated: {out_sql_path}")
    except Exception as e:
        print(f"  ✗ Error writing 05_permissions.sql: {e}")

def generate_sql_06_data(config: Dict[str, Any]) -> None:
    """
    Generates 06_data.sql in build/database.
    Seeds both 'pk_sequences' and 'users' tables from their respective CSVs.
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    db_dir = build_dir / 'database'
    db_dir.mkdir(parents=True, exist_ok=True)
    sql_path = db_dir / '06_data.sql'
    
    sql_lines = ["-- Seed Data for Secret Garden", ""]

    # --- Part 1: PK Sequences ---
    pk_csv = build_dir / 'pk_sequences.csv'
    if pk_csv.exists():
        values = []
        try:
            with open(pk_csv, 'r', newline='', encoding='utf-8') as f:
                reader = csv.reader(f)
                for row in reader:
                    if len(row) < 2: continue
                    domain = row[0].replace("'", "''")
                    seq = row[1].replace("'", "''")
                    values.append(f"('{domain}', '{seq}')")
            
            if values:
                sql_lines.append("-- 1. Seed pk_sequences")
                sql_lines.append("INSERT INTO pk_sequences (domain, pk_sequence) VALUES")
                sql_lines.append(",\n".join(values))
                sql_lines.append("ON CONFLICT (domain, pk_sequence) DO NOTHING;\n")
            else:
                sql_lines.append("-- No PK sequences found in CSV\n")
        except Exception as e:
            print(f"  ✗ Error reading pk_sequences.csv: {e}")
            sql_lines.append(f"-- Error reading pk_sequences.csv: {e}")
    else:
        sql_lines.append("-- Warning: pk_sequences.csv not found")

    # --- Part 2: Users (Usernames/Displaynames) ---
    users_csv = build_dir / 'base_usernames.csv'
    if users_csv.exists():
        values = []
        try:
            with open(users_csv, 'r', newline='', encoding='utf-8') as f:
                reader = csv.reader(f)
                header = next(reader, None) # Skip header
                for row in reader:
                    if len(row) < 2: continue
                    username = row[0].replace("'", "''")
                    displayname = row[1].replace("'", "''")
                    values.append(f"('{username}', '{displayname}')")
            
            if values:
                sql_lines.append("-- 2. Seed users (Vending Pool)")
                sql_lines.append("INSERT INTO users (username, displayname) VALUES")
                sql_lines.append(",\n".join(values))
                sql_lines.append("ON CONFLICT (username) DO NOTHING;\n")
            else:
                sql_lines.append("-- No users found in base_usernames.csv\n")
        except Exception as e:
            print(f"  ✗ Error reading base_usernames.csv: {e}")
            sql_lines.append(f"-- Error reading base_usernames.csv: {e}")
    else:
        sql_lines.append("-- Warning: base_usernames.csv not found")

    # Write final file
    try:
        with open(sql_path, 'w', encoding='utf-8') as f:
            f.write("\n".join(sql_lines))
        print(f"✓ SQL data script generated: {sql_path}")
    except Exception as e:
        print(f"  ✗ Error writing 06_data.sql: {e}")

def generate_base_usernames_csv(config: Dict[str, Any]) -> None:
    """
    Generates base-usernames.csv in build/.
    Creates a pool of 'username' and 'displayname' entries.
    
    Format:
      - username: "adjective_noun" (e.g. "obsidian_falcon")
      - displayname: "Adjective Noun" (e.g. "Obsidian Falcon")
      
    Keyspace: 300 x 300 = 90,000 unique combinations.
    Count: defined by project_meta.num_generated_usernames
    """
    global BUILD_DIR, REPO_ROOT
    repo_root = REPO_ROOT if REPO_ROOT is not None else Path(__file__).resolve().parents[2]
    build_dir = BUILD_DIR if BUILD_DIR is not None else (repo_root / 'build')
    
    csv_path = build_dir / 'base_usernames.csv'
    
    # Target count from specific config variable
    project_meta = config.get("project_meta", {})
    target_count = project_meta.get("num_generated_usernames", 100)
    
    # 300 Adjectives
    adjectives = [
        "absurd", "acidic", "active", "actual", "adept", "agile", "alert", "alive", "alpine", "amber",
        "ancient", "angry", "arcade", "arcane", "arctic", "arid", "armed", "astral", "atomic", "auto",
        "autumn", "aware", "azure", "basic", "black", "blank", "blind", "blonde", "blue", "bold",
        "brave", "brief", "bright", "broad", "bronze", "brown", "bumpy", "busy", "calm", "cheap",
        "chief", "civil", "clean", "clear", "clever", "cloudy", "cold", "cool", "cosmic", "crazy",
        "creepy", "crisp", "cruel", "cryptic", "curly", "cyan", "daily", "damp", "daring", "dark",
        "deadly", "dear", "deep", "dense", "digital", "direct", "divine", "dizzy", "double", "drab",
        "dry", "dual", "dull", "dusty", "dynamic", "eager", "early", "earthy", "easy", "electric",
        "elite", "empty", "epic", "equal", "eternal", "exact", "exotic", "faint", "fair", "fake",
        "fancy", "fast", "fatal", "feral", "fierce", "filthy", "fine", "firm", "first", "fixed",
        "flat", "fluid", "flying", "fond", "fragile", "free", "fresh", "frosty", "frozen", "full",
        "funny", "future", "fuzzy", "giant", "gifted", "glass", "global", "glowing", "gold", "golden",
        "good", "grand", "gray", "great", "green", "grey", "grim", "gross", "happy", "hard",
        "harsh", "hazy", "heavy", "heroic", "hidden", "high", "hollow", "holy", "honest", "hot",
        "huge", "humble", "humid", "hush", "hyper", "icy", "idle", "indigo", "infinite", "inner",
        "iron", "jade", "jolly", "just", "keen", "kind", "large", "last", "late", "lazy",
        "leafy", "lean", "legal", "light", "lime", "little", "live", "living", "local", "lone",
        "long", "loose", "lost", "loud", "lovely", "loyal", "lucky", "lunar", "mad", "magic",
        "magma", "main", "major", "manual", "many", "marble", "master", "mean", "mega", "mellow",
        "merry", "metal", "mild", "mini", "minor", "mint", "misty", "mobile", "modern", "moody",
        "mossy", "motion", "muddy", "muted", "mystic", "narrow", "native", "navy", "near", "neat",
        "neon", "new", "nice", "night", "noble", "noisy", "north", "novel", "null", "ocean",
        "odd", "old", "olive", "omega", "only", "onyx", "open", "orange", "outer", "pale",
        "past", "peace", "pearl", "pink", "plain", "plastic", "polar", "polite", "poor", "prime",
        "prism", "proud", "pure", "purple", "quick", "quiet", "radio", "rainy", "rapid", "rare",
        "raw", "ready", "real", "red", "regal", "retro", "rich", "ripe", "rising", "risky",
        "robust", "rocky", "rose", "rough", "round", "royal", "ruby", "rude", "rustic", "rusty",
        "sacred", "safe", "sage", "salty", "sandy", "sane", "savage", "scarlet", "secret", "secure",
        "senior", "shadow", "sharp", "shiny", "short", "shy", "sick", "silent", "silky", "silly",
        "silver", "simple", "single", "sleek", "slim", "slow", "small", "smart", "smooth", "snowy",
        "soft", "solar", "solid", "solo", "sonic", "sour", "spare", "spark", "spicy", "spiky",
        "spiral", "spirit", "stable", "static", "steady", "steel", "steep", "sticky", "stiff", "still",
        "stone", "stormy", "strict", "strong", "sturdy", "subtle", "sudden", "sunny", "super", "sweet",
        "swift", "tall", "tame", "tart", "teal", "tech", "tender", "tense", "thin", "tidy",
        "tight", "tiny", "tired", "top", "tough", "toxic", "true", "twin", "ugly", "ultra",
        "unique", "urban", "vague", "vain", "vast", "velvet", "vexed", "vibrant", "video", "violet",
        "viral", "virtual", "vital", "vivid", "void", "warm", "wary", "weak", "weird", "west",
        "wet", "white", "whole", "wide", "wild", "windy", "winged", "winter", "wise", "witty",
        "wooden", "wrong", "yellow", "young", "zen", "zero", "zinc", "zone", "zoom"
    ]
    
    # 300 Nouns
    nouns = [
        "ace", "agent", "alpha", "anchor", "angel", "apex", "arch", "area", "arena", "ark",
        "arm", "army", "arrow", "art", "ash", "atom", "audio", "aura", "auto", "axe",
        "axis", "badge", "band", "bank", "bar", "base", "bat", "bay", "beam", "bear",
        "beast", "beat", "bee", "bell", "belt", "beta", "bias", "bird", "bit", "blade",
        "blast", "blaze", "block", "blood", "bloom", "boat", "body", "bolt", "bomb", "bond",
        "bone", "book", "boom", "boot", "boss", "bot", "box", "brain", "brick", "bridge",
        "bug", "bulk", "bull", "byte", "cable", "cage", "cake", "call", "camp", "can",
        "cap", "car", "card", "case", "cat", "cave", "cell", "cent", "chain", "chaos",
        "chart", "chat", "chef", "chip", "city", "clan", "claw", "clay", "cliff", "clock",
        "cloud", "club", "clue", "coal", "coast", "coat", "cobra", "code", "coin", "cold",
        "cone", "core", "corn", "cost", "cow", "crab", "crash", "crew", "crow", "crown",
        "cube", "cult", "cup", "cycle", "dance", "dark", "dash", "data", "date", "dawn",
        "day", "deal", "deck", "deed", "deep", "deer", "delta", "demo", "den", "desk",
        "dial", "dice", "diet", "disc", "dish", "disk", "dock", "dog", "doll", "dome",
        "door", "dot", "dove", "drag", "draw", "dream", "drive", "drop", "drum", "duck",
        "dust", "duty", "eagle", "ear", "earth", "east", "echo", "edge", "eel", "egg",
        "elf", "elk", "elm", "end", "epic", "exit", "eye", "face", "fact", "fall",
        "fan", "farm", "fate", "fear", "feat", "feed", "fern", "file", "film", "fin",
        "fire", "fish", "fist", "flag", "flame", "flash", "flat", "flaw", "fleet", "flow",
        "flux", "fly", "foam", "fog", "folk", "font", "food", "foot", "force", "ford",
        "fork", "form", "fort", "fox", "frame", "frog", "frost", "fuel", "full", "fund",
        "fuse", "gain", "game", "gap", "gas", "gate", "gear", "gem", "gene", "ghost",
        "giant", "gift", "gig", "girl", "glass", "glory", "glove", "glow", "glue", "goal",
        "goat", "gold", "golf", "grab", "grid", "grip", "grit", "guard", "guest", "guide",
        "gulf", "gull", "gum", "gun", "guru", "hail", "hair", "hall", "halo", "hand",
        "hare", "harp", "hat", "hawk", "head", "heap", "heart", "heat", "helm", "help",
        "herb", "hero", "hill", "hint", "hive", "hole", "home", "hood", "hook", "hope",
        "horn", "hose", "host", "hour", "hub", "hull", "hunt", "hut", "ice", "icon",
        "idea", "idol", "image", "inch", "ink", "inn", "ion", "iron", "isle", "item",
        "jack", "jade", "jail", "jam", "jar", "jaw", "jay", "jazz", "jeep", "jet",
        "job", "jog", "join", "joke", "joy", "jump", "junk", "jury", "keep", "key",
        "kick", "kid", "kill", "kin", "king", "kiss", "kite", "kiwi", "knee", "knot",
        "lab", "lad", "lake", "lamb", "lamp", "land", "lane", "lark", "last", "law",
        "layer", "lead", "leaf", "leak", "lean", "leap", "lens", "life", "lift", "light",
        "lily", "lime", "line", "link", "lion", "lip", "list", "load", "lock", "log",
        "loop", "lord", "loss", "luck", "lump", "lung", "lure", "lush", "lynx", "mace",
        "mage", "magma", "mail", "main", "mall", "man", "map", "mark", "mars", "mask",
        "mass", "mast", "mate", "math", "maze", "meal", "meat", "mech", "melt", "memo",
        "menu", "mesh", "mess", "metal", "mile", "milk", "mill", "mind", "mine", "mint",
        "mist", "mite", "mix", "mob", "mode", "mold", "mole", "monk", "mood", "moon",
        "moose", "moss", "moth", "move", "mud", "mule", "muse", "musk", "nail", "name",
        "nano", "nap", "navy", "neck", "need", "neon", "nest", "net", "news", "next",
        "nice", "node", "noise", "noon", "north", "nose", "note", "noun", "nova", "null",
        "nut", "oak", "oar", "oat", "ocean", "odd", "oil", "old", "olive", "omen",
        "one", "onion", "onyx", "opal", "orb", "orc", "order", "owl", "ox", "pack",
        "page", "pain", "pair", "palm", "pan", "panel", "panic", "paper", "park", "part",
        "pass", "path", "pawn", "peak", "pear", "pearl", "pen", "pet", "phase", "phone",
        "photo", "pike", "pilot", "pine", "pipe", "pit", "plan", "plane", "plant", "plate"
    ]
    
    sysrand = random.SystemRandom()
    generated = set()
    rows = []
    
    keyspace = len(adjectives) * len(nouns)
    print(f"\nGenerating {target_count} usernames (Keyspace: {keyspace} combinations)...")
    
    attempts = 0
    max_attempts = target_count * 20 
    
    while len(rows) < target_count and attempts < max_attempts:
        attempts += 1
        adj = sysrand.choice(adjectives)
        noun = sysrand.choice(nouns)
        
        # Format: adjective_noun (no suffix)
        username = f"{adj}{noun}"
        displayname = f"{adj.title()} {noun.title()}"
        
        if username not in generated:
            generated.add(username)
            rows.append((username, displayname))
            
    try:
        with open(csv_path, 'w', newline='', encoding='utf-8') as f:
            writer = csv.writer(f)
            writer.writerow(['username', 'displayname'])
            writer.writerows(rows)
        print(f"✓ Usernames CSV generated: {csv_path}")
    except Exception as e:
        print(f"  ✗ Error writing base-usernames.csv: {e}")

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
        app_config['num_sequences_per_site'] = num_sequences_per_site

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

        # Generate PK sequences and export to CSV
        generate_pk_sequences(config)

        # Generate SQL script for roles
        generate_sql_01_roles(config)

        # Generate SQL script for tables
        copy_sql_template("02_tables.sql", "02_tables.sql")
        generate_sql_02_tables_extensions(config)

        # Copy SQL script for policies
        copy_sql_template("03_policies.sql", "03_policies.sql")

        # Copy SQL script for functions
        copy_sql_template("04_functions.sql", "04_functions.sql")

        # Generate SQL script for permissions
        generate_sql_05_permissions(config)

        # Generate base-usernames.csv if it does not already exist
        base_usernames_path = BUILD_DIR / "base_usernames.csv"
        user_provided_base_usernames_path = service_dir / "base-usernames.csv"
        if not user_provided_base_usernames_path.exists():
            generate_base_usernames_csv(config)
        else:
            print(f"✓ base-usernames.csv already exists at {user_provided_base_usernames_path}, copying into build directory.")
            shutil.copy(user_provided_base_usernames_path, base_usernames_path)

        # Generate SQL script for seeding data
        generate_sql_06_data(config)

        return 0
        
    except (FileNotFoundError, ValueError) as e:
        print(f"✗ Error: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
