// PostToolUse (Edit|Write): format the edited PHP file with php-cs-fixer, inside Docker.
// Never blocks: formatting is a convenience, the CI is the judge.
'use strict';

const { execSync } = require('node:child_process');
const { readInput, projectRoot, relativePath } = require('./lib');

const input = readInput();
const file = relativePath(input, input.tool_input && input.tool_input.file_path);
if (!file.endsWith('.php') && !file.startsWith('bin/')) process.exit(0);

try {
  execSync(`make fmt FILE=${JSON.stringify(file)}`, { cwd: projectRoot(input), stdio: 'ignore', timeout: 120000 });
} catch {
  // Docker unavailable or fixer error: leave the file as is, the CI will tell.
}
process.exit(0);
