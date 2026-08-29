# Fixture `counters-no-memory`

## Ce qu'elle prouve

Le cas « compteurs > 0 et `agents_md = false` », explicitement marqué **non vérifié** par la
spec (`docs/specs/02-axe-harness.md` § Règle) : aucun des quatre profils fournis n'est dans ce
cas. La grille cumule — behavior n'existe pas sans context engineering — donc un compteur de
règles positif sans fichier mémoire ne peut pas faire sauter l'axe à Copper ; il retombe à
« prompts » (Red), avec une note qui dit pourquoi.

## Construction

- `context_files.rules_count = 2` (compteur > 0).
- `context_files.agents_md = false`.
- Les autres axes sont réglés pour ne pas plafonner en dessous de Harness : Taille bande M
  (Blue), Intervention médiane 1 (Copper), En parallèle médiane 1 (Green) — tous au-dessus de
  Red.

## Niveau attendu

Harness = **Red** (« prompts »), avec la note « des règles/agents sont comptés sans fichier
mémoire ; la grille cumule, le niveau ne peut pas sauter context engineering ». Niveau global
**Red**, plafonné par le seul axe Harness.
