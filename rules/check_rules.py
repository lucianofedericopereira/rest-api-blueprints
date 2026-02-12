#!/usr/bin/env python3
"""
Validate that every file listed in iso27001-rules.yaml exists on disk.

Usage: python3 rules/check_rules.py
Exit:  0 = all files found, 1 = one or more files missing
"""
import sys
import yaml
from pathlib import Path

ROOT = Path(__file__).parent.parent
REGISTRY = Path(__file__).parent / "iso27001-rules.yaml"

PROJECT_ROOTS = {
    "fastapi":  ROOT / "iso27001-fastapi",
    "symfony":  ROOT / "iso27001-symfony" / "src",
    "laravel":  ROOT / "iso27001-laravel" / "app",
}

# Symfony src root differs — map the registry paths accordingly
SYMFONY_SRC = ROOT / "iso27001-symfony" / "src"


def main() -> int:
    with open(REGISTRY) as f:
        data = yaml.safe_load(f)

    errors: list[str] = []

    for rule in data["rules"]:
        rid = rule["id"]
        for stack, files in [
            ("fastapi", rule.get("fastapi", [])),
            ("symfony", rule.get("symfony", [])),
            ("laravel", rule.get("laravel", [])),
        ]:
            for rel in files:
                if stack == "symfony":
                    # registry paths are relative to src/
                    full = ROOT / "iso27001-symfony" / rel
                elif stack == "laravel":
                    full = ROOT / "iso27001-laravel" / rel
                else:
                    full = ROOT / "iso27001-fastapi" / rel

                if not full.exists():
                    errors.append(f"  [{rid}] {stack}: {rel}  →  {full}")

    if errors:
        print(f"check-rules FAILED — {len(errors)} missing file(s):\n")
        for e in errors:
            print(e)
        return 1

    rules_count = len(data["rules"])
    print(f"check-rules OK — all files present ({rules_count} rules verified)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
