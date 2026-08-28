# 02 — Axe Harness

Définition source : « ce que la personne a mis en place autour du modèle. **Context
engineering** : ce que l'IA sait (mémoire, architecture, conventions). **Behavior** : comment
elle agit (règles, agents, hooks, guardrails). **Boucles** : un script relance l'IA tant qu'une
commande du projet échoue, jusqu'à ce qu'elle passe ».

⚠️ La grille a **cinq** valeurs (rien, prompts, context engineering, + behavior, + boucles) ;
le paragraphe de définition n'en décrit que trois. « prompts » n'existe que dans la grille.

## Signal

`git-activity.json → context_files` : `agents_md` (bool), `rules_count`, `skills_count`,
`hooks_count`, `agents_count`. Compteurs déjà normalisés, **indépendants de l'outil et de
l'emplacement** — c'est ce qui répond à « jamais la marque » : `leodagan` range sa mémoire
dans `aidd_docs/memory/`, `arthur` dans `docs/context/`, et leurs compteurs sont remplis.

Rattachement (la grille tranche, pas de taxonomie externe) : un agent ou un hook est
**toujours** Behavior, jamais Context engineering.

## Règle

| Valeur | Condition (dans l'ordre, la plus haute qui tient) | Niveau |
|---|---|---|
| boucles | behavior **et** une boucle de relance bornée détectée (ci-dessous) | Gold |
| behavior | context engineering **et** `rules_count + skills_count + hooks_count + agents_count > 0` | Copper (Green et Copper partagent la cellule) |
| context engineering | `agents_md = true` | Blue |
| prompts | rien de ce qui précède, `commits.ai_coauthored_ratio > 0` | Red |
| rien | `ai_coauthored_ratio = 0` et aucun compteur | White |

Les niveaux se cumulent : « behavior » sans `agents_md` n'existe pas dans la grille. Cas
constaté : compteurs > 0 et `agents_md = false` → valeur **prompts**, avec une note « des
règles/agents sont comptés sans fichier mémoire ; la grille cumule, le niveau ne peut pas
sauter context engineering ». ⚠️ **Non vérifié** sur donnée réelle, aucun profil fourni
n'est dans ce cas.

## Boucles — le seul signal absent du fichier

`git-activity.json` n'a **aucun champ** pour les boucles. Détection dans `repo-context/`,
sur le modèle des règles 8 et 9 du brief : un fichier de CI, un `Makefile` ou un script
d'orchestration qui **relance** une commande du projet sur échec **et** porte une **borne
visible** (nombre d'essais ou budget). Les deux ensemble ; une relance sans borne n'est pas
une boucle au sens de la grille, c'est un risque.

Mise en œuvre : recherche de motifs dans `repo-context/` — fichiers sous `.github/workflows/`,
`Makefile`, `scripts/`, `*.sh`, `*.js`, `*.ts`, `*.py` ; motif de relance (`retry`, `until`,
`while`, `attempt`, `rerun`) **et** motif de borne (`max_attempts`, `max-retries`,
`MAX_ITER`, `budget`, entier ≤ 20 associé). Liste **non vérifiée** — aucun profil fourni
n'a de boucle ; `arthur` a un brainstorm `2026-06-auto-retry.md` explicitement « Not
decided », qui ne doit **pas** être détecté comme boucle (un fichier de `docs/brainstorm/`
n'est pas un script). Sans `repo-context/`, boucles = non détectable → l'axe plafonne à
Copper avec la note « boucles : non observable, `repo-context/` absent ».

Preuve structurelle ≠ preuve fonctionnelle : un hook déclaré n'est pas un hook qui tourne.
Sur cet axe, le plancher n'est pas un volume mais une **preuve** : présence structurelle
(compteur > 0) et, quand `repo-context/` est là, **au moins un fichier cité** qui la matérialise
(chemin réel du dossier). Compteur > 0 sans aucun fichier trouvé → note d'incohérence, la
valeur reste celle du compteur (la source du compteur est l'opérateur, pas l'outil).

## Preuves rendues

- `git-activity.json › context_files.agents_md = true → context engineering`
- `git-activity.json › context_files.{rules:3, skills:3, hooks:1, agents:2} → behavior`
- `repo-context/.claude/hooks/check-assertions.js` (câblé dans `repo-context/.claude/settings.json`)
- `repo-context/ › aucune relance bornée trouvée → boucles absentes`

## Actionnabilité

**Actionnable** : c'est un artefact qui s'écrit aujourd'hui. Toujours recommandé en premier ;
geste précis et vérifiable (« écrire un fichier mémoire à la racine », « ajouter un hook et le
câbler », « ajouter une étape de relance plafonnée dans la CI »). Voir § 06.

## Tests

`perceval` → prompts/Red · `bohort` → context eng./Blue · `leodagan` et `arthur` →
behavior/Copper · fixture avec Makefile `retry` borné → boucles/Gold · fixture `arthur` sans
`repo-context/` → Copper + note · fixture ratio 0 et compteurs 0 → White.
