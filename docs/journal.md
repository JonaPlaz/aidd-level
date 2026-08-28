# Journal d'exécution

Ce qui n'a **pas** produit de commit : tentative abandonnée, liaison cassée, repli, borne
atteinte, choix d'allocation revu. Git, les PR, les commentaires de review et la CI
journalisent le reste. Fichier **append-only**, une ligne par événement, chaque ligne porte un
pointeur vérifiable — sans pointeur elle ne vaut rien.

| horodatage | étape / chantier | acteur | événement | pointeur | suite |
|---|---|---|---|---|---|
| 2026-08-28T21:23Z | amorçage | session Claude Code · fable · défaut | fichiers racine écrits, `composer.lock` généré dans Docker, commit initial portant les specs | `f65e92a` | — |
| 2026-08-28T21:23Z | amorçage | session Claude Code · fable · défaut | `gh repo create --public --source=. --push` : dépôt créé ; classifier auto-mode a refusé la commande groupée (create + labels + protection), rejouée en trois commandes | https://github.com/JonaPlaz/aidd-level | découper les commandes externes, une par appel |
| 2026-08-28T21:23Z | amorçage | session Claude Code · fable · défaut | protection de `main` par `gh api PUT …/branches/main/protection` : HTTP 200, `required_linear_history`, 0 approbation requise, sans check requis (CI absente) — doute 5 levé | `repos/JonaPlaz/aidd-level/branches/main/protection` | second PUT avec check `ci` au montage du harnais |
| 2026-08-28T21:23Z | amorçage | session Claude Code · fable · défaut | fin de l'amorçage ; geste manuel restant : activer la revue Codex (réglage web) | — | PR jetable ouverte pour lever les doutes 1–3 |
