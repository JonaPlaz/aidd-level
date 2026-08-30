---
name: feature
description: Démarre un chantier depuis une issue GitHub — spec si absente (arrêt humain pour validation), puis implémentation, PR, une revue Codex à l'ouverture, une passe de correction avec réponse tracée, merge automatique. Spec : docs/specs/08-harnais.md.
argument-hint: [issue-number] [--trivial]
---

# /feature <n°>

Issue → spec → dev → PR → review → merge. Un seul point d'arrêt humain : la validation d'une
spec nouvelle. Tout le reste s'enchaîne. **Le skill possède la boucle de revue et le merge ;
l'agent `dev` rend la main dès la PR ouverte.** Lancé par un agent `front` (chantier 17), il ne
travaille jamais dans le checkout principal : `W` est alors le worktree de l'agent `dev`.

## 0. Verrou du cycle

Le hook `guard-git` refuse `gh pr create` hors d'un run de ce skill et tout `gh pr merge`
synchrone. Premier geste, avant tout : `node .claude/hooks/feature-lock.js lock <n°>`
(`lock trivial-<horodatage>` en mode `--trivial`). Le verrou est **propre à ce run** : il
n'autorise que les branches qui portent le numéro (`feat/<n°>-…`, `docs/spec-<n°>` ;
`trivial/…` pour le mode trivial). Dernier geste, sur toute sortie (mergé, `blocked`, spec à
valider) : `node .claude/hooks/feature-lock.js unlock <n°>` — jamais le verrou d'un autre run.
Un verrou du même numéro déjà présent à l'entrée = un run précédent s'est arrêté sans
nettoyer : le signaler au journal, continuer (`lock` est idempotent). Les deux commandes sont
dans la liste `allow` de `.claude/settings.json` : aucun arrêt pour permission.

## 1. Routage (une seule fois — iron rule)

- `gh issue view <n°>` : lire le titre, le corps, la spec citée.
- Spec citée présente dans `docs/specs/` **et committée sur `origin/main`** → étape 2.
- Absente → lancer l'agent `spec`, puis **s'arrêter** : « spec écrite, à valider ». Ne pas
  poursuivre dans la même invocation. Les questions de l'agent vivent dans son **rendu**,
  jamais dans le fichier.
- **Réponses reçues → relancer l'agent `spec` avec elles → il les intègre au texte normatif
  et supprime toute question → alors seulement le commit** (docs/specs/08-harnais.md § 12.2).
- **Spec validée, présente dans l'arbre de travail, non committée** (sans question ouverte —
  déjà validée telle quelle, ou déjà intégrée par la branche précédente) → même chemin : le
  contrôle avant commit ci-dessous, puis le commit.

  Les deux branches convergent sur le contrôle avant commit, sur les lignes ajoutées aux
  specs par cette PR — pas le dépôt entier, pas même le fichier entier :

  ```
  for f in $(git diff --name-only origin/main -- docs/specs;
             git ls-files --others --exclude-standard -- docs/specs); do
    git add -N -- "$f"                                  # sans quoi une spec neuve reste invisible
    git diff -U0 origin/main -- "$f" | sed -n 's/^+//p' \
      | grep -Ei '^#{1,6} *\**(questions? ouvertes?|arbitrages?)|^[[:space:]]*[-*>]?[[:space:]]*\**(question ouverte|à trancher|à valider pa)|\?[[:space:]]*$' \
      | grep -vi historique
  done
  ```

  Toute occurrence arrête le commit : la spec repart à l'agent `spec` pour intégration, pas
  au commit. Sans occurrence → branche `docs/spec-<n°>`, commit, push, PR `docs:` avec label
  `to-review`, boucle de l'étape 3. `--auto` n'est pas un merge synchrone : **attendre
  `gh pr view <pr> --json mergedAt` non nul** (toutes les 60 s, même plafond), puis
  `git fetch origin main`, puis étape 2. Le worktree de l'agent `dev` part d'`origin/main` :
  une spec qui n'y est pas n'existe pas pour lui.
- `--trivial` : pas d'agent, une ligne ajoutée au README, PR ouverte, étape 3.

## 2. Implémentation

Lancer l'agent `dev` avec le numéro d'issue. Il travaille en worktree, ouvre la PR avec le
label `to-review` et `Closes #<n°>`, et rend la main.

Corps de PR : résumé fonctionnel en deux lignes, spec appliquée, décisions de scoring
touchées et le test qui les fige, commandes passées (`make test lint dup`).

## 3. Revue et merge

`REVIEW_WAIT_MAX = 20 min` — valeur initiale posée d'après la PR #13 (revue Codex reçue
≈ 12 min après le dernier push) ; `docs/harness.md` enregistre les délais constatés ensuite.

**Une revue Codex par PR, à l'ouverture.** Arbitré par Jonathan le 2026-08-29 : le quota de
revues Codex a été épuisé en une soirée par les re-revues (3 à 5 par PR) ; la boucle
« jusqu'à validation » du même jour est remplacée. Ce qui tient lieu de validation : la CI,
les tests qui figent chaque décision, et **une réponse tracée à chaque remarque**.

`W` est le checkout qui a créé la PR : le worktree de l'agent `dev`, ou le checkout courant
pour une PR `docs/spec-<n°>` ou `--trivial`. Les opérations git sur la branche sont exécutées
par le propriétaire de `W` (l'agent `dev` relancé, ou le skill lui-même) ; jamais de `git -C`.

1. **Attendre la revue d'ouverture.** Relever `T0` = heure d'ouverture. Toutes les 60 s
   (`sleep 60`), lire avec `gh api --paginate` `repos/{owner}/{repo}/pulls/<pr>/reviews`,
   `…/pulls/<pr>/comments` et `…/issues/<pr>/reactions`, en ne retenant que les revues de
   `chatgpt-codex-connector[bot]` et ses réactions créées après `T0` (`eyes` = pris en charge,
   n'arrête pas l'attente ; `+1` = rien à signaler). Plafond `REVIEW_WAIT_MAX` ; dépassé →
   label `blocked`, journal, arrêt. Un commentaire du bot annonçant un quota épuisé → idem,
   avec le message dans le journal. **Rien ne s'arme avant ce verdict.**
2. **Corriger, une passe.** Remarques → relancer l'agent `dev` sur la même branche avec
   toutes les remarques ; il corrige tout, repousse, rend la main. Une remarque non appliquée
   se justifie, elle ne s'ignore pas.
3. **Répondre à chaque remarque**, dans le fil de la PR : un commentaire récapitulatif, une
   ligne par remarque (`fichier:ligne — appliqué en <sha>` ou `non appliqué : <motif>`).
   C'est la trace que le jury lit à la place d'un verdict Codex.
4. **Rebaser.** Le check `ci` est requis en mode `strict`. Le propriétaire de `W` :
   `git fetch origin && git rebase origin/main && git push --force-with-lease` (`--force`,
   `-f`, `+refspec`, `--mirror` refusés par le hook `guard-git`). Conflit → une tentative,
   puis `blocked`, journal, arrêt.
5. **Armer le merge** : `gh pr view <pr> --json mergeStateStatus` ≠ `BEHIND`, puis
   `gh pr merge <pr> --auto --squash --delete-branch`, puis attendre, toutes les 60 s et sous
   `REVIEW_WAIT_MAX`, `gh pr view <pr> --json mergedAt,statusCheckRollup` : `mergedAt` non
   nul → fini ; check en échec ou plafond → `gh pr merge <pr> --disable-auto`, label
   `blocked`, journal, arrêt.
6. **Re-revue : jamais automatique.** Seulement si la correction change une décision de
   scoring (seuil, niveau, règle du minimum) ou sur demande de Jonathan — alors
   `@codex review` après le rebase, retour à l'étape 1 avec le nouveau SHA et un nouveau `T0`.
7. **Après le merge**, quand `W` est un worktree d'agent : `git worktree remove W` puis
   `git branch -D <branche>`.

## 4. Après le merge

Ajouter une ligne à `ROADMAP.md` (état : mergé, PR, SHA) dans la PR suivante ou une PR
`docs:` dédiée. Jamais de réécriture du fichier.
