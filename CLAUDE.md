@AGENTS.md

# Règles propres à Claude Code

- Lire la spec concernée dans `docs/specs/` avant toute implémentation ; ne jamais implémenter
  un comportement qui la contredit — signaler l'écart et s'arrêter.
- Un commit touche une seule couche (`src/Domain/`, `src/Application/`,
  `src/Infrastructure/`, `tests/`) ou la documentation. Le hook `guard-git` refuse
  domaine + infrastructure ensemble.
- Branche de feature courte, rebase sur `main`, jamais de merge de `main` dans la branche
  (`git fetch origin && git rebase origin/main && git push --force-with-lease` ; `--force`
  nu est refusé par hook). Une tentative de rebase automatique ; en cas de conflit,
  s'arrêter et journaliser.
- `ROADMAP.md` et `docs/journal.md` : ajouter des lignes en fin de fichier, ne jamais
  réécrire. Une ligne de journal sans pointeur (SHA, PR, run, chemin) ne vaut rien.
- Ne jamais écrire dans `.brief/`, ne jamais le committer, ne jamais le citer dans le code
  ou la documentation publique.
- Les commandes du projet passent par `make` (Docker). Ne pas lancer `php`, `composer` ni
  `vendor/bin/*` directement.
- Messages de commit en anglais, Conventional Commits, un sujet de 72 caractères maximum.
- **Tout commit produit par un agent porte le trailer** `Co-Authored-By: Claude <noreply@anthropic.com>`
  — c'est le signal que la grille lit (« commit signé par un assistant ») ; sans lui, le dépôt
  se note lui-même à tort (constaté : ratio 0,48 sur `profiles/self`).

## Flow d'une PR — imposé par le harnais

- **Toute PR naît d'une issue et passe par `/feature <n°>`**, docs comprises. Le skill est
  invocable par la session ; le hook `guard-git` refuse `gh pr create` hors d'un run du skill
  (verrou par issue, `node .claude/hooks/feature-lock.js lock|unlock <n°>`) et tout
  `gh pr merge` synchrone (seuls `--auto` et `--disable-auto` passent).
- Ce que le skill garantit, dans l'ordre : label `to-review` ; attente du verdict Codex
  (`eyes` = en cours, `+1` = sans remarque, revue `COMMENTED` = remarques inline) ; une passe
  de correction ; **rebase et push d'abord**, puis une réponse tracée par remarque citant le SHA
  présent sur la branche ; `@codex review` seulement si la correction change un seuil, un
  niveau ou la règle du minimum ; `gh pr merge --auto --squash --delete-branch` dans tous les
  cas ; ligne au journal avec pointeur.
- Le cron `auto-merge-after-codex` (chantier 15) n'est qu'un filet pour un 👍 resté sans suite.
- **`/roadmap` ouvre les fronts prêts** (dépendances mergées sur GitHub, spec présente, aucun
  verrou ni chevauchement de sorties) et lance un agent `front` par front, en arrière-plan ;
  `/feature` reste le cycle unitaire qu'il précharge (chantier 17).
- Trois mots reconnus par la session pour la roadmap : « **pause roadmap** » (plus de nouveau
  front, les fronts en cours vont au bout), « **stop roadmap** » (idem, plus arrêt des fronts
  en cours et désarmement des PR), « **reprends la roadmap** » (seule façon de repartir).
