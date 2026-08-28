// PreToolUse (Edit|Write): src/Domain must never import Application or Infrastructure.
// Rule 1 of AGENTS.md. Blocks with exit 2 before the file is written.
'use strict';

const { readInput, relativePath, block } = require('./lib');

const FORBIDDEN_IN_DOMAIN = [/use\s+AiddLevel\\Application\\/, /use\s+AiddLevel\\Infrastructure\\/];

const input = readInput();
const file = relativePath(input, input.tool_input && input.tool_input.file_path);
if (!file.startsWith('src/Domain/')) process.exit(0);

const content = (input.tool_input && (input.tool_input.content ?? input.tool_input.new_string)) || '';
for (const pattern of FORBIDDEN_IN_DOMAIN) {
  if (pattern.test(content)) {
    block(`guard-layers: ${file} is in src/Domain and must not import ${pattern.source.replace(/\\\\/g, '\\')} (AGENTS.md rule 1).`);
  }
}
process.exit(0);
