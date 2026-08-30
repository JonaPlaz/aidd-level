---
name: roadmap
description: Ouvre en parallèle tous les fronts prêts de la roadmap — dépendances mergées, spec présente, aucun verrou, aucune sortie partagée. Invocable par le modèle dès le premier message de Jonathan, quel qu'en soit le sujet.
argument-hint: [--dry-run]
---

# /roadmap <spec 08 § 11>

Décide, ne code pas, ne touche pas à git (hors le `fetch` borné ci-dessous). Trois profondeurs :
session → agent `front` → agent `dev` (spec 08 § 11.2).

## 0. Verrou de sélection

`node .claude/hooks/roadmap-ready.js lock` — **pas** `feature-lock.js` : la sélection a besoin
d'une exclusion atomique (le premier arrivé gagne), pas d'un verrou idempotent. La commande
balaie d'abord un verrou `roadmap-selector` abandonné (invocation morte en route — pid mort ou
plus vieux que `ROADMAP_SCAN_TIMEOUT × 3`, signalé au journal), puis tente une création
exclusive (`wx`, refusée par l'OS si le fichier existe déjà) ; l'id qui y est inscrit
(horodatage ms + pid du parent) ne sert qu'à l'audit, l'exclusion tient à l'existence du
fichier lui-même.

**Code de sortie non nul → le perdant sort immédiatement, sans scanner, en le disant** — ne pas
« remplir les créneaux libres » au passage, le tir suivant de la tâche de remplissage le fera
dans `ROADMAP_REFILL_INTERVAL`.

Le verrou est tenu **jusqu'à la réservation des verrous par numéro** (fin de l'étape 2) et
**retiré sur chaque chemin terminal, sans exception** (`node .claude/hooks/roadmap-ready.js
unlock`) : file vide, aucun front ouvrable, frein global, pause, erreur de scan, dépassement de
`ROADMAP_SCAN_TIMEOUT`. C'est **cette invocation** qui le retire — jamais un `front`, jamais une
invocation suivante.

## 1. Scan

`node .claude/hooks/roadmap-ready.js` — seule implémentation de la règle de maturité (spec 08
§ 11.3), une échéance unique (`ROADMAP_SCAN_TIMEOUT`) posée une fois pour tout le scan et
partagée par chaque appel `gh` restant, elle-même précédée d'un `git fetch origin main` borné
(`FETCH_TIMEOUT`). Le script ne bloque jamais : il imprime freins, fronts prêts et retenus,
fronts non retenus, chantiers « à spécifier », écarts (dépendance fermée sans PR mergée) — ou,
si le scan a dû s'interrompre (`ROADMAP.md` illisible, `gh` en échec, échéance dépassée), un
scan **dégradé** explicitement distingué d'« aucun front prêt » (qui, lui, dit qu'un scan
complet n'a rien trouvé) — et sort 0.

- Pause, frein global (`blocked` sur une PR ou une issue ouverte, état distant inconnu) ou scan
  dégradé → **rien ne s'ouvre** ; retirer le verrou de sélection, annoncer le motif en une
  ligne, s'arrêter.
- Un chantier listé « à spécifier » n'ouvre rien et ne consomme pas de créneau : une spec
  nouvelle est le seul arrêt humain (§ 7). **Le traiter avant de conclure quoi que ce soit** —
  lancer l'agent `spec` dessus **une seule fois par passage**, comme le fait `/feature`, puis
  continuer avec les fronts qui, eux, sont prêts.
- Aucun front prêt **et** rien à spécifier → retirer le verrou de sélection, annoncer « file
  vide », s'arrêter (rien à planifier). Si l'agent `spec` a été lancé à l'étape précédente, ce
  n'est **pas** une file vide : l'annoncer comme telle (« rien à ouvrir, une spec est en
  rédaction »), pas comme « rien à faire ».
- `--dry-run` : afficher le tableau (fronts retenus, non retenus, à spécifier, freins) et
  s'arrêter avant toute réservation ni tout lancement, sans lancer l'agent `spec` non plus —
  retirer le verrou de sélection avant de sortir.

## 2. Réservation (sélection atomique)

Pour chaque front retenu par le scan (déjà ordonné par **numéro de chantier** croissant, déjà
plafonné à `MAX_CONCURRENT_FRONTS`, déjà purgé des chevauchements de sorties) :
`node .claude/hooks/feature-lock.js lock <n°>` (`<n°>` est le numéro d'**issue**, pas le
numéro de chantier). `feature-lock.js lock` reste idempotent — il réserve, il n'exclut pas ;
c'est le verrou de sélection posé à l'étape 0 qui porte l'exclusion entre deux invocations
concurrentes de `/roadmap`. On garde, pour la suite de la conversation, la liste des numéros
réservés par ce passage : « stop roadmap » (§ 6) en a besoin pour savoir lesquels déverrouiller.

Puis, geste terminal de cette fenêtre : `node .claude/hooks/roadmap-ready.js unlock`.

## 3. Lancement — un agent `front` par numéro réservé, en arrière-plan

Pour chaque numéro réservé à l'étape 2, lancer explicitement un sous-agent `front`
(`.claude/agents/front.md`) **en arrière-plan** (background) avec l'issue en argument. Ne pas
attendre son retour : la session doit se rendre à Jonathan dans la foulée (§ 11.9, épreuve).
Le `front` trouve son verrou déjà posé, ne le repose pas, et c'est lui qui le retire à son pas
terminal (mergé, `blocked`, borne atteinte).

## 4. Tâche de remplissage

Au premier passage qui ouvre au moins un front, planifier (`CronCreate`) une tâche récurrente
`ROADMAP_REFILL_INTERVAL = 10 min` dont l'invite est `/roadmap` — alignée sur le cron
`auto-merge-after-codex` (chantier 15). Un réveil de session inactive à la fin d'un sous-agent
d'arrière-plan est non vérifié hors `/goal` (spec 08 § 11.5) : ce cron est le rattrapage, pas
une horloge — un tir manqué n'est pas repris.

**La boucle porte sa borne** : annuler cette tâche (`CronDelete`) dès qu'il ne reste ni front
prêt ni front en cours (`node .claude/hooks/roadmap-ready.js` scan à vide et aucun verrou par
numéro restant), ou dès qu'un frein global s'applique. À défaut, la plateforme l'expire seule
au bout de 7 jours.

## 5. Compte rendu

Un tableau, une ligne par front lancé (numéro, issue, statut « en cours »), puis rendre la
main immédiatement — pas d'attente. À chaque retour d'un `front` (notification d'achèvement
dans un tour ultérieur), rappeler `/roadmap` pour remplir le créneau libéré.

## 6. Ce que Jonathan garde (spec 08 § 11.7)

Trois mots reconnus par la session, jamais par un hook :

- **« pause roadmap »** : `node .claude/hooks/roadmap-ready.js pause pause` — plus aucun front
  nouveau, `CronDelete` sur la tâche de remplissage ; les fronts en cours vont au bout.
- **« stop roadmap »** : `node .claude/hooks/roadmap-ready.js pause stop`, puis `TaskStop` sur
  chaque `front` en cours. Le `front` stoppé n'atteint jamais son pas terminal, donc ne retire
  jamais son propre verrou : la session **retire explicitement le verrou numérique de chaque
  front qu'elle a lancé** (`node .claude/hooks/feature-lock.js unlock <n°>`, pour chaque numéro
  de la liste tenue à l'étape 2), et `gh pr merge <n> --disable-auto` sur les PR déjà armées.
  Une ligne de journal par front arrêté. Les worktrees gardent leur travail.
- **« reprends la roadmap »** : `node .claude/hooks/roadmap-ready.js resume` retire le
  marqueur, puis relancer `/roadmap` **sur un état propre** — les verrous numériques des
  fronts arrêtés ayant déjà été retirés ci-dessus, le scan qui suit les retrouve libres. Seule
  façon de repartir.

Le marqueur `roadmap-paused` survit à la session (`/clear`, redémarrage) : le hook
`SessionStart` l'annonce à la place du tableau tant qu'il est là.

## Ce que ce skill ne fait jamais

- Il n'écrit rien au journal lui-même — un seul propriétaire de la ligne par front : le
  `front`. Ce qui s'ajoute mécaniquement (`journal.js` sur `SubagentStop`) n'est pas un
  doublon, il documente autre chose (la fin d'un agent, pas ce qu'il a produit).
- Il ne touche jamais au checkout principal au-delà du `fetch` borné : `guard-git` le refuse
  dès que plus d'un verrou par numéro existe.
- Il ne pose jamais `blocked`, ne le lève jamais : ce sont des gestes de `front` et de
  Jonathan.
