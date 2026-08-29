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
  ou la documentation publique.
- Les commandes du projet passent par `make` (Docker). Ne pas lancer `php`, `composer` ni
  `vendor/bin/*` directement.
- Messages de commit en anglais, Conventional Commits, un sujet de 72 caractères maximum.
- **Tout commit produit par un agent porte le trailer** `Co-Authored-By: Claude <noreply@anthropic.com>`
  — c'est le signal que la grille lit (« commit signé par un assistant ») ; sans lui, le dépôt
  se note lui-même à tort (constaté : ratio 0,48 sur `profiles/self`).
