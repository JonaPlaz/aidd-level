// PostToolUseFailure (Bash) and SubagentStop: append one line to docs/journal.md.
// The journal records what produced no commit; every line carries a pointer
// (HEAD sha, branch). Written by a hook, not by the model's goodwill.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');
const { readInput, projectRoot } = require('./lib');

const input = readInput();
const root = projectRoot(input);

function git(args) {
  try {
    return execSync(`git ${args}`, { cwd: root, encoding: 'utf8' }).trim();
  } catch {
    return '?';
  }
}

const event = input.hook_event_name || 'unknown';
let what;
if (event === 'PostToolUseFailure') {
  const command = ((input.tool_input && input.tool_input.command) || '').replace(/\|/g, '\\|').slice(0, 120);
  // Only project commands matter; a failed `ls` is noise.
  if (!/\b(make|git rebase|git push|gh pr|gh api|composer|docker)\b/.test(command)) process.exit(0);
  what = `commande échouée : \`${command}\``;
} else if (event === 'SubagentStop') {
  what = `agent terminé : ${input.agent_type || input.agent_name || 'inconnu'}`;
} else {
  process.exit(0);
}

const line = `| ${new Date().toISOString().slice(0, 16)}Z | ${git('rev-parse --abbrev-ref HEAD')} | hook \`journal.js\` (${event}) | ${what} | \`${git('rev-parse --short HEAD')}\` | — |\n`;
try {
  fs.appendFileSync(path.join(root, 'docs', 'journal.md'), line);
} catch {
  // Journal unwritable: nothing to do, git history still records commits.
}
process.exit(0);
