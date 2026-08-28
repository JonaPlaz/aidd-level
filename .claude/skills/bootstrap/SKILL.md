---
name: bootstrap
description: Amorçage du dépôt depuis les specs validées — fichiers racine, git init, dépôt distant, labels, protection de branche. Exécuté une seule fois. Spec : docs/specs/07-amorcage.md.
disable-model-invocation: true
allowed-tools: Bash Read Write
---

# Amorçage — une seule exécution

Refuser de tourner si `.git` existe déjà. Suivre `docs/specs/07-amorcage.md` étape par étape,
lire le code retour de chaque commande externe, journaliser chaque étape dans
`docs/journal.md` avec son pointeur.

1. Vérifier les préconditions : `docs/specs/` validé, pas de `.git`, `.brief/` présent.
2. Écrire les fichiers racine listés dans la spec (étape 1). `.brief/` en première ligne du
   `.gitignore`, déclaré dans `.worktreeinclude`.
3. `make build` pour générer `composer.lock` dans Docker.
4. `git init -b main` · `git add -A` · vérifier `git ls-files | grep -c '^\.brief/'` = 0 ·
   commit `chore: bootstrap repository from validated specs`.
5. `gh repo create aidd-level --public --source=. --remote=origin --push`.
6. Labels `to-implement`, `to-review`, `blocked`.
7. `gh repo edit --enable-auto-merge --delete-branch-on-merge` — sans ce réglage,
   `gh pr merge --auto` est refusé (constaté sur #14).
8. Protection de `main` par `gh api -X PUT …/branches/main/protection` avec la charge de la
   spec ; en cas de code ≠ 200, journaliser et laisser la protection à poser à la main.
9. S'arrêter et annoncer le seul geste manuel : activer la revue Codex (réglage web,
   *Revue du code*, revue automatique à l'ouverture de PR).
