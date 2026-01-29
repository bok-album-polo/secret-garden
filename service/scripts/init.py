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

import sys
import os
from pathlib import Path
from math import ceil
import yaml
from typing import Any, Dict, List, Optional
from jsonschema import validate, ValidationError, Draft7Validator


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


def get_service_path() -> Path:
    """Get the service directory path relative to the script location."""
    script_dir = Path(__file__).parent
    service_dir = script_dir.parent
    return service_dir


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


def main():
    """Main entry point."""
    # Parse command line arguments
    if len(sys.argv) > 1:
        # Use provided filename as-is
        filepath = Path(sys.argv[1])
    else:
        # Default to service/init.yaml
        service_dir = get_service_path()
        filepath = service_dir / "init.yaml"
    
    print(f"Validating configuration file: {filepath}")
    
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
        
        return 0
        
    except (FileNotFoundError, ValueError) as e:
        print(f"✗ Error: {e}")
        return 1


if __name__ == "__main__":
    sys.exit(main())
