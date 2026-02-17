#!/usr/bin/env ts-node
/**
 * DDD layer boundary enforcement for iso27001-nestjs.
 *
 * Enforces the same three contracts as iso27001-fastapi/.importlinter:
 *
 *   Contract 1 — domain-is-pure:
 *     src/domain/**  must NOT import from  src/infrastructure, src/api, src/core
 *
 *   Contract 2 — infrastructure-may-not-import-api:
 *     src/infrastructure/**  must NOT import from  src/api
 *
 *   Contract 3 — strict top-down layers:
 *     src/core/**  must NOT import from  src/api
 *     (api → core → domain is the only allowed direction)
 *
 * Run:  npx ts-node check-layers.ts
 * Make: make check-layers
 */

import * as fs   from 'fs';
import * as path from 'path';

// ── helpers ─────────────────────────────────────────────────────────────────

function walkTs(dir: string): string[] {
  const results: string[] = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) results.push(...walkTs(full));
    else if (entry.name.endsWith('.ts')) results.push(full);
  }
  return results;
}

/** Extract all relative or project-local imports from a TS source file. */
function extractImports(filePath: string): string[] {
  const src = fs.readFileSync(filePath, 'utf8');
  const imports: string[] = [];
  // Match: import ... from '...' and require('...')
  const re = /(?:from|require)\s*\(\s*['"]([^'"]+)['"]\s*\)|(?:from)\s+['"]([^'"]+)['"]/g;
  let m: RegExpExecArray | null;
  while ((m = re.exec(src)) !== null) {
    imports.push(m[1] ?? m[2]);
  }
  return imports;
}

/**
 * Resolve an import specifier to a layer name ('api'|'core'|'domain'|'infrastructure'|null).
 * Handles both relative paths and tsconfig-paths-style src/ paths.
 */
function resolveLayer(specifier: string, fromFile: string): string | null {
  let resolved: string;

  if (specifier.startsWith('.')) {
    resolved = path.resolve(path.dirname(fromFile), specifier);
  } else {
    // Bare specifier — check if it maps into our src tree
    resolved = path.resolve(process.cwd(), 'src', specifier);
  }

  const srcDir = path.resolve(process.cwd(), 'src');
  if (!resolved.startsWith(srcDir)) return null;

  const rel = resolved.slice(srcDir.length + 1);
  const layer = rel.split(path.sep)[0];
  return ['api', 'core', 'domain', 'infrastructure'].includes(layer) ? layer : null;
}

// ── contracts ────────────────────────────────────────────────────────────────

interface Violation {
  file: string;
  importedLayer: string;
  specifier: string;
  contract: string;
}

const violations: Violation[] = [];
const srcDir = path.resolve(process.cwd(), 'src');

function check(
  contractName: string,
  sourceLayer: string,
  forbiddenLayers: string[],
): void {
  const layerDir = path.join(srcDir, sourceLayer);
  if (!fs.existsSync(layerDir)) return;

  for (const file of walkTs(layerDir)) {
    for (const spec of extractImports(file)) {
      const target = resolveLayer(spec, file);
      if (target && forbiddenLayers.includes(target)) {
        violations.push({ file, importedLayer: target, specifier: spec, contract: contractName });
      }
    }
  }
}

// Contract 1 — Domain layer must not import infrastructure, api, or core
check('domain-is-pure', 'domain', ['infrastructure', 'api', 'core']);

// Contract 2 — Infrastructure must not import api layer
check('infrastructure-may-not-import-api', 'infrastructure', ['api']);

// Contract 3 — Core must not import api layer (top-down only)
check('strict-top-down-layers', 'core', ['api']);

// ── report ───────────────────────────────────────────────────────────────────

if (violations.length === 0) {
  console.log('✓ DDD layer boundaries: all 3 contracts passed (iso27001-nestjs)');
  process.exit(0);
} else {
  console.error(`\n✗ DDD layer boundary violations found (${violations.length}):\n`);
  for (const v of violations) {
    const rel = path.relative(process.cwd(), v.file);
    console.error(`  [${v.contract}]`);
    console.error(`    ${rel}`);
    console.error(`    imports from layer '${v.importedLayer}': "${v.specifier}"\n`);
  }
  process.exit(1);
}
