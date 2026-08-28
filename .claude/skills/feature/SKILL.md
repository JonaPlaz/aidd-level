---
name: feature
description: Démarre un chantier depuis une issue GitHub — spec si absente (arrêt humain pour validation), puis implémentation, PR, attente de la revue Codex, une passe de correction, merge automatique. Spec : docs/specs/08-harnais.md.
argument-hint: [issue-number] [--trivial]
disable-model-invocation: true
---

# /feature <n°>

Issue → spec → dev → PR → review → merge. Un seul point d'arrêt humain : la validation d'une
spec nouvelle. Tout le reste s'enchaîne. **Le skill possède la boucle de revue et le merge ;
l'agent `dev` rend la main dès la PR ouverte.**

## 1. Routage (une seule fois — iron rule)

- `gh issue view <n°>` : lire le titre, le corps, la spec citée.
- Spec citée présente dans `docs/specs/` **et committée sur `origin/main`** → étape 2.
- Présente mais non committée (validée à l'invocation précédente) → branche
  `docs/spec-<n°>`, commit, push, PR `docs:` avec label `to-review`, boucle de l'étape 3.
  `--auto` n'est pas un merge synchrone : **attendre `gh pr view <pr> --json mergedAt`
  non nul** (toutes les 60 s, même plafond), puis `git fetch origin main`, puis étape 2. Le
  worktree de l'agent `dev` part d'`origin/main` : une spec qui n'y est pas n'existe pas
  pour lui.
- Absente → lancer l'agent `spec`, puis **s'arrêter** : « spec écrite, à valider ». Ne pas
  poursuivre dans la même invocation.
- `--trivial` : pas d'agent, une ligne ajoutée au README, PR ouverte, étape 3.

## 2. Implémentation

Lancer l'agent `dev` avec le numéro d'issue. Il travaille en worktree, ouvre la PR avec le
label `to-review` et `Closes #<n°>`, et rend la main.

Corps de PR : résumé fonctionnel en deux lignes, spec appliquée, décisions de scoring
touchées et le test qui les fige, commandes passées (`make test lint dup`).

## 3. Revue et merge

`REVIEW_WAIT_MAX = 20 min` — valeur initiale posée d'après la PR #13 (revue Codex reçue
≈ 12 min après le dernier push) ; `docs/harness.md` enregistre les délais constatés ensuite.

**Autant de passes de correction qu'il en faut : tant que Codex ne valide pas, Claude Code
corrige.** Arbitré par Jonathan le 2026-08-29 sur la PR #15, en remplacement de la borne
« une passe » de la spec 08 § 7. Les bornes restantes : `maxTurns` par relance, le plafond
d'attente, et l'arrêt manuel.

1. **Rebaser d'abord.** Le check `ci` est requis en mode `strict` : une branche en retard sur
   `main` ne peut pas merger. `git fetch origin && git rebase origin/main`, puis
   `git push --force-with-lease` (autorisé ; `--force` nu est refusé par le hook
   `guard-git`). Conflit → une seule tentative, puis label `blocked`, journal, arrêt.
2. **Attendre le verdict sur le SHA courant.** Relever `HEAD` et l'horodatage `T0` de la
   demande. Toutes les 60 s (`sleep 60`), lire `gh api repos/{owner}/{repo}/pulls/<pr>/reviews`,
   `…/pulls/<pr>/comments` et `…/issues/<pr>/reactions`, en ne retenant que : les revues de
   `chatgpt-codex-connector[bot]` dont `commit_id` = `HEAD`, les commentaires rattachés à
   ces revues, et les réactions du bot créées après `T0`. Tout ce qui précède est du verdict
   périmé et ne compte pas. Une réaction `eyes` signifie « pris en charge », elle ne termine
   pas l'attente. Plafond : `REVIEW_WAIT_MAX`.
3. Réaction `+1`, ou revue sans commentaire inline → `gh pr merge <pr> --auto --squash
   --delete-branch`. **`--auto` ne s'arme jamais avant le verdict** : le merge auto GitHub
   ne connaît que la CI.
4. Commentaires → passe de correction : relancer l'agent `dev` sur la même branche avec les
   commentaires, repush, **commenter `@codex review`** (Codex ne re-revoit pas sur un push,
   constaté sur #14), puis revenir à l'étape 1 avec le nouveau `HEAD` et un nouveau `T0`.
5. Délai dépassé → label `blocked`, ligne de journal avec l'URL de la PR, arrêt.

## 4. Après le merge

Ajouter une ligne à `ROADMAP.md` (état : mergé, PR, SHA) dans la PR suivante ou une PR
`docs:` dédiée. Jamais de réécriture du fichier.
