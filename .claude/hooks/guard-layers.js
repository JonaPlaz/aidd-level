// PreToolUse (Edit|Write): src/Domain must never import Application or Infrastructure.
// Rule 1 of AGENTS.md. The prospective full file is checked (an Edit may only change
// a fragment of an existing import), and the write is blocked with exit 2.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { readInput, projectRoot, relativePath, block } = require('./lib');

const FORBIDDEN_IN_DOMAIN = [/use\s+AiddLevel\\Application\\/, /use\s+AiddLevel\\Infrastructure\\/];

const input = readInput();
const toolInput = input.tool_input || {};
const file = relativePath(input, toolInput.file_path);
if (!file.startsWith('src/Domain/')) process.exit(0);

function prospectiveContent() {
  if (input.tool_name === 'Write') return toolInput.content || '';
  let current = '';
  try {
    current = fs.readFileSync(path.join(projectRoot(input), file), 'utf8');
  } catch {
    current = '';
  }
  const oldString = toolInput.old_string || '';
  const newString = toolInput.new_string || '';
  if (!oldString) return current + newString;
  return toolInput.replace_all ? current.split(oldString).join(newString) : current.replace(oldString, newString);
}

const content = prospectiveContent();
for (const pattern of FORBIDDEN_IN_DOMAIN) {
  if (pattern.test(content)) {
    block(`guard-layers: ${file} is in src/Domain and must not import ${pattern.source.replace(/\\\\/g, '\\')} (AGENTS.md rule 1).`);
  }
}
process.exit(0);
