// PreToolUse (Bash): a `git commit` must not stage src/Domain and src/Infrastructure
// together (AGENTS.md rule 7), and must never stage .brief/.
'use strict';

const { execSync } = require('node:child_process');
const { readInput, projectRoot, block } = require('./lib');

const input = readInput();
const command = (input.tool_input && input.tool_input.command) || '';
if (!/\bgit\s+commit\b/.test(command)) process.exit(0);

let staged = [];
try {
  staged = execSync('git diff --cached --name-only', { cwd: projectRoot(input), encoding: 'utf8' })
    .split('\n')
    .filter(Boolean);
} catch {
  process.exit(0);
}

if (staged.some((f) => f.startsWith('.brief/'))) {
  block('guard-commit: .brief/ is private and must never be committed.');
}
const touchesDomain = staged.some((f) => f.startsWith('src/Domain/'));
const touchesInfra = staged.some((f) => f.startsWith('src/Infrastructure/'));
if (touchesDomain && touchesInfra) {
  block('guard-commit: a commit must not touch src/Domain and src/Infrastructure together (AGENTS.md rule 7). Split the commit.');
}
process.exit(0);
