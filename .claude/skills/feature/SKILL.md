---
name: feature
description: Démarre un chantier depuis une issue GitHub — spec si absente (arrêt humain pour validation), puis implémentation, PR, revue Codex, passes de correction jusqu'à validation, merge automatique. Spec : docs/specs/08-harnais.md.
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

La branche vit dans le worktree de l'agent `dev` (`W`, chemin rapporté par l'agent) ; le skill
n'agit dessus que par `git -C W …`, jamais depuis le checkout principal.

1. **Rebaser.** Le check `ci` est requis en mode `strict` : une branche en retard sur `main`
   ne peut pas merger. `git -C W fetch origin && git -C W rebase origin/main`, puis
   `git -C W push --force-with-lease` (seule forme admise ; `--force`, `-f` et les refspecs
   `+…` sont refusés par le hook `guard-git`). Conflit → une seule tentative, puis label
   `blocked`, journal, arrêt.
2. **Demander le verdict sur ce SHA.** Relever `HEAD = git -C W rev-parse HEAD`, puis
   l'horodatage `T0`, puis commenter `@codex review` — dans cet ordre, toujours après le
   rebase (Codex ne re-revoit pas sur un push ; une demande faite avant le rebase porte sur
   un SHA qui n'existe plus). À l'ouverture de la PR, la revue part d'elle-même : `T0` =
   heure d'ouverture, pas de commentaire.
3. **Attendre.** Toutes les 60 s (`sleep 60`), lire avec `gh api --paginate`
   `repos/{owner}/{repo}/pulls/<pr>/reviews`, `…/pulls/<pr>/comments` et
   `…/issues/<pr>/reactions`, en ne retenant que : les revues de
   `chatgpt-codex-connector[bot]` dont `commit_id` = `HEAD`, les commentaires rattachés à
   ces revues, et les réactions du bot créées après `T0`. Tout ce qui précède est périmé. Une
   réaction `eyes` signifie « pris en charge », elle ne termine pas l'attente. Plafond :
   `REVIEW_WAIT_MAX` ; dépassé → label `blocked`, ligne de journal avec l'URL, arrêt.
4. **Commentaires** → passe de correction : relancer l'agent `dev` sur la même branche avec
   les commentaires ; il corrige, repousse, rend la main. Retour à l'étape 1.
5. **Réaction `+1` ou revue sans commentaire inline** → revérifier la base :
   `git -C W fetch origin` ; si `origin/main` a avancé depuis le rebase de l'étape 1
   (`git -C W merge-base --is-ancestor origin/main HEAD` faux), retour à l'étape 1 — le
   verdict portait sur un SHA qui ne mergera pas. Sinon
   `gh pr merge <pr> --auto --squash --delete-branch`, puis attendre `mergedAt` non nul.
   **`--auto` ne s'arme jamais avant le verdict ni sur une base périmée** : le merge auto
   GitHub ne connaît que la CI et ne rebase pas.

## 4. Après le merge

Ajouter une ligne à `ROADMAP.md` (état : mergé, PR, SHA) dans la PR suivante ou une PR
`docs:` dédiée. Jamais de réécriture du fichier.
