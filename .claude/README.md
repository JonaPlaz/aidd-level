# Le harnais IA de ce dépôt (`.claude/`)

Panneau descriptif : qui fait quoi dans l'outillage IA de ce dépôt. Les règles, les seuils et
le détail des verrous restent en spec — ce panneau nomme et pointe, il ne les recopie pas.
Sources : `docs/specs/08-harnais.md`, `AGENTS.md § Flow d'une PR`, `docs/harness.md` (le flow
tel qu'il a réellement tourné).

## Agents

- `spec` (`.claude/agents/spec.md`) — écrit ou complète une spécification à partir d'une
  issue, pose ses questions dans son rendu, n'écrit jamais de code. Ne travaille pas en
  worktree.
- `dev` (`.claude/agents/dev.md`) — implémente une issue dont la spec est validée : branche,
  code, tests, PR. Travaille en worktree isolé.
- `front` (`.claude/agents/front.md`) — tient un cycle `/feature` complet pour un front
  réservé par `/roadmap` : agent `dev`, revue, correction, merge, déverrouillage. Lancé en
  arrière-plan.

Modèle, effort et bornes par agent sont des valeurs, pas un rôle : elles vivent en
`docs/specs/08-harnais.md` § 2 et § 11.5, absentes d'ici à dessein.

## Skills

- `bootstrap` (`.claude/skills/bootstrap/SKILL.md`) — amorce le dépôt une seule fois depuis
  les specs validées. Non invocable par le modèle.
- `feature` (`.claude/skills/feature/SKILL.md`) — le cycle unitaire : issue → spec → agent
  `dev` → PR → revue → merge. Possède la boucle de revue et le merge, jamais l'agent `dev`.
- `roadmap` (`.claude/skills/roadmap/SKILL.md`) — ouvre en parallèle les fronts prêts et lance
  un agent `front` par front.

## `.claude/hooks/`

Cinq scripts câblés sur un événement (`.claude/settings.json`) :

- `guard-layers.js` — `PreToolUse` (Edit/Write) : refuse qu'un fichier de `src/Domain/`
  importe `Application/` ou `Infrastructure/`.
- `guard-git.js` — `PreToolUse` (Bash) : encadre le commit par couche, le `push`, et la PR
  hors cycle `/feature`.
- `format.js` — `PostToolUse` (Edit/Write) : formate le fichier PHP modifié, ne bloque jamais.
- `journal.js` — `PostToolUseFailure` (Bash), `SubagentStop`, `Stop` : ajoute une ligne à
  `docs/journal.md`.
- `roadmap-ready.js` — `SessionStart` : affiche la maturité des fronts ; le même script sert
  aussi de décision pour `/roadmap`.

Deux scripts sans événement câblé, appelés en ligne de commande :

- `feature-lock.js` — le verrou par issue que pose et lève le skill `feature` ; c'est ce
  verrou que `guard-git.js` exige avant de laisser passer un `gh pr create`.
- `lib.js` — la bibliothèque partagée par les scripts ci-dessus.

`.claude/hooks/tests/` porte les tests par violation de `guard-git.js` et de
`roadmap-ready.js`.

Règle exacte de chaque hook et détail du verrou : `docs/specs/08-harnais.md` § 3 et § 4.

## Flow d'une PR, en cinq lignes

1. Une issue portant `to-implement`.
2. `/feature <n°>` : spec si absente (arrêt humain), sinon agent `dev` en worktree.
3. PR ouverte, label `to-review`.
4. Revue Codex à l'ouverture, une passe de correction, réponses tracées.
5. `gh pr merge --auto --squash --delete-branch`.

Garanties détaillées et ordre exact : `AGENTS.md § Flow d'une PR`.

## Pilotage de la roadmap

Trois mots reconnus par la session : « pause roadmap », « stop roadmap », « reprends la
roadmap ». Leur effet précis : `AGENTS.md § Flow d'une PR`.
