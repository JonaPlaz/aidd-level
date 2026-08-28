---
name: feature
description: Démarre un chantier depuis une issue GitHub — spec si absente (arrêt humain pour validation), puis implémentation, PR, attente de la revue Codex, une passe de correction, merge automatique. Spec : docs/specs/08-harnais.md.
argument-hint: [issue-number] [--trivial]
disable-model-invocation: true
---

# /feature <n°>

Issue → spec → dev → PR → review → merge. Un seul point d'arrêt humain : la validation d'une
spec nouvelle. Tout le reste s'enchaîne.

## 1. Routage (une seule fois — iron rule)

- `gh issue view <n°>` : lire le titre, le corps, la spec citée.
- Spec citée présente dans `docs/specs/` → étape 2.
- Absente → lancer l'agent `spec`, puis **s'arrêter** : « spec écrite, à valider ». Ne pas
  poursuivre dans la même invocation.
- `--trivial` : pas d'agent, une ligne ajoutée au README, PR ouverte, étape 3.

## 2. Implémentation

Lancer l'agent `dev` avec le numéro d'issue. Il travaille en worktree, ouvre la PR avec le
label `to-review` et `Closes #<n°>`.

Corps de PR : résumé fonctionnel en deux lignes, spec appliquée, décisions de scoring
touchées et le test qui les fige, commandes passées (`make test lint dup`).

## 3. Revue et merge

1. Interroger `gh api repos/{owner}/{repo}/pulls/<pr>/reviews` et `…/comments` toutes les
   60 s, jusqu'à `REVIEW_WAIT_MAX` (valeur fixée dans `docs/harness.md` d'après la PR jetable).
2. Aucune remarque (revue `APPROVED` ou `COMMENTED` sans demande) →
   `gh pr merge <pr> --auto --squash --delete-branch`.
3. Remarques → **une** passe de correction par l'agent `dev` sur la même branche, repush,
   puis `gh pr merge --auto`. La re-revue, si elle bloque, laisse la PR : label `blocked`,
   ligne de journal, arrêt.
4. Délai dépassé → label `blocked`, ligne de journal avec l'URL de la PR, arrêt.

## 4. Après le merge

Ajouter une ligne à `ROADMAP.md` (état : mergé, PR, SHA) dans la PR suivante ou une PR
`docs:` dédiée. Jamais de réécriture du fichier.
