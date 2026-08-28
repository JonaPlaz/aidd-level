// PostToolUseFailure (Bash), SubagentStop and Stop: append one line to docs/journal.md.
// The journal records what produced no commit; every line carries a pointer
// (HEAD sha, branch). Written by a hook, not by the model's goodwill.
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { execSync } = require('node:child_process');
const { readInput, projectRoot, parseGitAll } = require('./lib');

const input = readInput();
const command = (input.tool_input && input.tool_input.command) || '';
const gitInvocations = parseGitAll(command);
const parsedGit = gitInvocations.find((g) => ['rebase', 'push', 'merge', 'cherry-pick'].includes(g.subcommand)) || gitInvocations[0] || null;
// A `git -C <worktree> …` failure is journaled against that worktree's HEAD and branch.
const root = parsedGit && parsedGit.cPath ? path.resolve(projectRoot(input), parsedGit.cPath) : projectRoot(input);

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
  const shown = command.replace(/\|/g, '\\|').slice(0, 120);
  // Only project commands matter; a failed `ls` is noise. Git is matched on its parsed
  // subcommand so `git -C <worktree> rebase` counts like `git rebase`.
  const gitMatters = parsedGit && ['rebase', 'push', 'merge', 'cherry-pick'].includes(parsedGit.subcommand);
  if (!gitMatters && !/\b(make|gh pr|gh api|composer|docker)\b/.test(command)) process.exit(0);
  what = `commande échouée : \`${shown}\``;
} else if (event === 'SubagentStop') {
  what = `agent terminé : ${input.agent_type || input.agent_name || 'inconnu'}`;
} else if (event === 'Stop') {
  // A turn that ends off main with uncommitted work is an outcome without a commit:
  // a bound reached, an abandoned attempt. On main, or with a clean tree, nothing to record.
  if (input.stop_hook_active) process.exit(0);
  const branch = git('rev-parse --abbrev-ref HEAD');
  const dirty = git('status --porcelain --untracked-files=all');
  if (branch === 'main' || dirty === '') process.exit(0);
  what = `fin de tour avec travail non committé sur \`${branch}\` (${dirty.split('\n').length} fichier(s))`;
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
