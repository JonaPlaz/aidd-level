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

1. Toutes les 60 s (`sleep 60`, autorisé par `settings.json`), lire
   `gh api repos/{owner}/{repo}/pulls/<pr>/reviews`, `…/pulls/<pr>/comments` **et
   `…/issues/<pr>/reactions`** (le 👍 « rien à signaler » de Codex est une réaction sur la
   PR, pas une revue), jusqu'à une revue ou une réaction `+1` de
   `chatgpt-codex-connector[bot]`, ou `REVIEW_WAIT_MAX`. Une réaction `eyes` signifie « pris
   en charge », elle ne termine pas l'attente.
2. Réaction `+1`, ou revue sans commentaire inline → `gh pr merge <pr> --auto --squash
   --delete-branch`.
3. Commentaires → **une** passe de correction : relancer l'agent `dev` sur la même branche
   avec les commentaires, repush, puis `gh pr merge <pr> --auto --squash --delete-branch`.
   Codex re-revoit la PR ; s'il commente encore, la PR reste : label `blocked`, ligne de
   journal, arrêt. Pas de seconde passe.
4. Délai dépassé → label `blocked`, ligne de journal avec l'URL de la PR, arrêt.

## 4. Après le merge

Ajouter une ligne à `ROADMAP.md` (état : mergé, PR, SHA) dans la PR suivante ou une PR
`docs:` dédiée. Jamais de réécriture du fichier.
