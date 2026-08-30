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

## Flow d'une PR — ne jamais improviser

- Une issue → `/feature <n°>` : le skill porte spec, dev, revue, merge. À la main seulement pour
  une PR de docs ou de profils, et alors **le même flow** :
  1. PR ouverte (`gh pr create`), label `to-review`, attendre le verdict Codex : réaction `eyes` = en cours,
     `+1` = sans remarque, revue `COMMENTED` = remarques inline (`gh api …/pulls/<n>/comments`).
  2. Remarques : une passe de correction, **rebase et push `--force-with-lease` d'abord**, puis
     **une réponse tracée par remarque** citant le SHA présent sur la branche (« appliqué en
     `<sha>` » ou « non appliqué, motif »). Si la correction change un seuil, un niveau ou la
     règle du minimum : `@codex review` avant d'armer (seule re-revue admise).
  3. Armer : `gh pr merge <n> --auto --squash --delete-branch`, **dans tous les cas**, 👍 compris.
     **Jamais de merge synchrone** (refusé par le classifieur, et hors flow). Le cron
     `auto-merge-after-codex` (chantier 15) n'est qu'un filet pour le 👍 oublié.
- Après merge : `git checkout main && git pull`, ligne au journal avec pointeur.

