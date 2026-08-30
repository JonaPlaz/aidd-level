# Le harnais IA de ce dépôt (`.claude/`)

Qui fait quoi, quel événement déclenche quel script ou agent, et où passe la frontière entre ce
qui décide seul (déterministe) et ce qui écrit (probabiliste, un modèle). Règles, seuils et
détail des verrous restent en spec — ce panneau nomme et pointe, il ne les recopie pas. Sources :
`docs/specs/08-harnais.md`, `AGENTS.md § Flow d'une PR`, `docs/harness.md`.

## Agents

- `spec` (`.claude/agents/spec.md`) — écrit ou complète une spec depuis une issue, pose ses questions dans son rendu ; pas de worktree.
- `dev` (`.claude/agents/dev.md`) — implémente une issue dont la spec est validée : branche, code, tests, PR ; en worktree isolé.
- `front` (`.claude/agents/front.md`) — tient un cycle `/feature` complet pour un front réservé par `/roadmap` ; lancé en arrière-plan.

Modèle, effort, bornes : `docs/specs/08-harnais.md` § 2 et § 11.5, valeurs absentes d'ici.

## Skills

- `bootstrap` (`.claude/skills/bootstrap/SKILL.md`) — amorce le dépôt une fois, non invocable par le modèle.
- `feature` (`.claude/skills/feature/SKILL.md`) — cycle unitaire issue → spec → agent `dev` → PR → revue → merge ; possède la boucle de revue.
- `roadmap` (`.claude/skills/roadmap/SKILL.md`) — ouvre en parallèle les fronts prêts, un agent `front` par front, en arrière-plan.

## `.claude/hooks/` — événements réels

Cinq scripts câblés (`.claude/settings.json`) :

- `guard-layers.js` — `PreToolUse` (`Edit|Write`) : refuse un `use` de `src/Domain/` vers `Application/`/`Infrastructure/`.
- `guard-git.js` — `PreToolUse` (`Bash`) : filtre `git commit`, `git push`, `gh pr create`, `gh pr merge`.
- `format.js` — `PostToolUse` (`Edit|Write`) : reformate le `.php` modifié, ne bloque jamais.
- `journal.js` — `PostToolUseFailure` (`Bash`), `SubagentStop`, `Stop` : ajoute une ligne à `docs/journal.md`.
- `roadmap-ready.js` — `SessionStart` (`startup|resume|clear`) : affiche les fronts prêts ; sert aussi de décision à `/roadmap`.

Deux scripts sans événement, en ligne de commande :

- `feature-lock.js` — verrou par issue posé/levé par `feature` ; `guard-git.js` l'exige avant un `gh pr create`.
- `lib.js` — bibliothèque partagée des scripts ci-dessus.

Tests par violation : `.claude/hooks/tests/`. Règle exacte de chaque hook : `docs/specs/08-harnais.md` § 3 et § 4.

## La chaîne d'événements

Un message de Jonathan invoque `/roadmap` ou `/feature <n°>` (skills invocables par le modèle) —
jamais un hook, qui ne fait qu'injecter du contexte au démarrage (`SessionStart`) :

```
message de Jonathan → skill /roadmap (ou /feature <n°> direct)
  └─► agent front (par front retenu) → skill feature préchargé
        └─► agent dev (worktree) : code, tests
              → gh pr create   [guard-git.js PreToolUse : verrou du numéro exigé]
              → PR + label to-review          [CI côté GitHub : pull_request]
              → Codex revoit à l'ouverture (réglage cloud, seul geste manuel)
              → skill : correction, réponses tracées, rebase
              → gh pr merge --auto            [guard-git.js PreToolUse : --auto exigé]
              → mergedAt non nul → feature-lock.js unlock <n°>
        cron auto-merge-after-codex (schedule) : filet si le 👍 Codex reste sans suite
fin de chaque agent → journal.js (SubagentStop)
```

## Frontière déterministe / probabiliste

Décide seul, sur des faits lus (`gh`, disque, git), sans modèle : les hooks (`guard-layers`,
`guard-git`, `format`, `journal`, `roadmap-ready`), les verrous, le cron, la CI — un `grep`, un
exit 2, une commande armée si une condition tient.

Écrit et raisonne, un modèle : la session (routage), l'agent `spec` (le texte normatif), l'agent
`dev` (code, tests), l'agent `front` (cette remarque touche-t-elle un seuil ?), Codex (la revue).

La frontière passe **à la porte de chaque outil** : un agent propose `git commit`, `gh pr create`
ou `gh pr merge` — le hook déterministe décide s'il passe, jamais l'inverse.

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

## Scénario narratif : cinq features, trois menées de front

Cinq issues `to-implement`, sorties disjointes. Un message de Jonathan invoque `/roadmap` :

```
5 issues to-implement
  └─► /roadmap : fetch borné, roadmap-ready.js, verrou roadmap-<horodatage>
        ├─► tri : dépendances mergées sur GitHub, pas de blocked, pas de verrou déjà
        │   posé, pas de chevauchement de sorties, spec présente → 5 candidats
        ├─► MAX_CONCURRENT_FRONTS retenus (n° croissant) : 3 verrouillés, 2 en
        │   attente ; verrou roadmap-* retiré (fin de la sélection)
        └─► 3 agents front, chacun en worktree, en arrière-plan :
              front #A → dev #A → PR #A → Codex → correction → merge auto → unlock
              front #B → dev #B → PR #B → Codex → correction → merge auto → unlock
              front #C → dev #C → PR #C → Codex → correction → merge auto → unlock
front #B mergé en premier → créneau libre
  └─► tâche planifiée (ROADMAP_REFILL_INTERVAL) rappelle /roadmap
        └─► un 4e candidat prêt prend le créneau ; sinon rien ne s'ouvre
```

Le worktree isole le code de chaque front ; le verrou isole son numéro (aucune autre PR dessus
tant qu'il tient) ; le checkout principal reste en lecture seule pour git dès que deux verrous
coexistent (`guard-git.js`). Rien n'attend d'un front à l'autre, sauf le plafond de créneaux.
