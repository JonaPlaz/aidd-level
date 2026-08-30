@AGENTS.md

# Règles propres à Claude Code

- Skills invocables par la session : `/feature` (cycle unitaire), `/roadmap` (ouvre les
  fronts prêts) ; agents : `spec`, `dev`, `front`.
- Hooks versionnés dans `.claude/settings.json` (§ 4, contenu non redit ici) ; worktrees pour
  les agents en parallèle.
- Verrous : `node .claude/hooks/feature-lock.js lock|unlock <n°>` et
  `node .claude/hooks/roadmap-ready.js pause|resume`.
- Trailer de coauteur, chaîne exacte : `Co-Authored-By: Claude <noreply@anthropic.com>`
  (motif et règle : `AGENTS.md` § Conventions).
- Une commande externe par appel : le classifieur refuse les commandes groupées (journal
  `2026-08-28T21:23Z`).
