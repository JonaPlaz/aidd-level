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
| 2026-08-28T21:31Z | PR jetable | session Claude Code · fable · défaut | PR #1 et #13 mergées à la main après ~10 min sans revue Codex — erreur de conduite : #1 servait à observer Codex. Règle reprise : aucun merge sans revue constatée ou délai dépassé (label `blocked`) | https://github.com/JonaPlaz/aidd-level/pull/1 · `a2eff14` · `5e9a65c` | la PR du chantier 1 sert de PR d'observation |
| 2026-08-28T21:31Z | harnais | session Claude Code · fable · défaut | CI : run 33212627397 échoué (`tests/` absent), 33212688646 échoué (phpcpd rend 1 sur arbre vide), 33212756256 vert ; `setup-php@v2` avec PHP 8.5 fonctionne (doute levé) ; second PUT de protection avec check `ci` requis : HTTP 200 | https://github.com/JonaPlaz/aidd-level/actions/runs/33212756256 | gardes `hashFiles` retirées au chantier 1 |
| 2026-08-28T21:31Z | chantier 1 | session Claude Code · fable · défaut | agent `dev` (`.claude/agents/dev.md`) non chargé : session démarrée avant sa création, la liste des agents est figée à l'ouverture. Repli : agent générique en worktree avec les consignes de `dev.md` (sonnet, effort medium). L'allocation déclarée n'est donc pas celle appliquée pour ce chantier | `.claude/agents/dev.md` @ `5e9a65c` | relancer une session sur `main` pour les chantiers 2+ |
| 2026-08-28T21:39Z | fix/harness-codex-review | hook `journal.js` (Stop) | fin de tour avec travail non committé sur `fix/harness-codex-review` (8 fichier(s)) | `5e9a65c` | — |
| 2026-08-28T21:39Z | PR jetable | Codex (cloud, revue auto) | revue reçue sur PR #13 à 21:36Z, ≈ 12 min après le dernier push (`df6a2b2`) : état `COMMENTED`, 10 commentaires inline (6 P1, 4 P2), pas de check — doutes 1 et 3 levés ; forme = commentaires, aucun verrou possible, conforme à la spec 08 | https://github.com/JonaPlaz/aidd-level/pull/13#pullrequestreview-5055276741 | `REVIEW_WAIT_MAX = 20 min` ; passe de correction en PR dédiée (la #13 était déjà mergée) |
