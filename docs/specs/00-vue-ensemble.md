# 00 — Vue d'ensemble

> Spécification produit. Source unique : le brief du hackathon (`.brief/hackathon-laivel-up.md`,
> jamais versionné). Ce qui porte un chiffre ou un nom a été revérifié à la source le 2026-08-28
> (dépôt `ai-driven-dev/laivel-up`, commit `89b9e35`). Ce qui n'a pas pu l'être est marqué
> **non vérifié**.

## 1. Ce que fait l'outil

Un CLI qui lit **un dossier de profil** (les pièces fournies par le sujet) et rend, en texte :

1. **un niveau** de la grille AIDD (White → Gold) ;
2. **ce qui a mené là** — l'axe qui plafonne, avec chaque fait et l'endroit où il se constate ;
3. **comment monter d'un cran** — un geste, ordonné par actionnabilité.

Aucun appel LLM à l'exécution, aucune clé d'API, aucune persistance, sortie texte seule.
Ces quatre points sont tranchés (brief, § *Ce qui est déjà tranché*) et ne se rouvrent pas.

## 2. La grille appliquée

`levels/aidd.md`, sept niveaux, quatre axes, revérifiée ligne à ligne le 2026-08-28 :

| Niveau | rang | Taille | Harness | Intervention | En parallèle |
|---|---|---|---|---|---|
| ❖ White | 0 | aucune | rien | — | 0 |
| 🔺 Red | 1 | S | prompts | après coup, sur la majorité | 1 |
| 🔹 Blue | 2 | M | context engineering | après coup, sur une partie | 1 |
| 🟢 Green | 3 | L | context engineering, behavior | aux étapes clés | 1 |
| 🥉 Copper | 4 | L-XL | context engineering, behavior | aux étapes clés | 3 |
| 🥈 Silver | 5 | L-XL | + boucles | jamais, une fois la tâche cadrée | 3 |
| 🥇 Gold | 6 | L-XL | + boucles | jamais, cadrage compris | 3 |

Trois règles de la source, appliquées telles quelles :

- « Un niveau n'est atteint que si **tous ses axes** le sont » → **le niveau est le minimum
  des quatre axes.**
- « Chaque cellule est un **minimum**, pas une valeur exacte » → un axe vaut **le plus haut
  niveau dont il satisfait la cellule** ; 4 chantiers satisfont « 3 », L satisfait « L-XL ».
- « La colonne "Ce qu'on observe" illustre, elle ne décide pas. »

Hors périmètre (source) : séniorité, qualité du code (prérequis, pas axe), volume d'usage.

## 3. Les données d'entrée

Un profil est **un dossier de mesures pré-agrégées**, pas un dépôt git. Huit pièces possibles,
jamais toutes présentes (`profiles/README.md`) :

| Pièce | Rôle dans le calcul |
|---|---|
| `profile.json` | identité ; tableau `available` = cohérence annoncé / présent |
| `git-activity.json` | **colonne vertébrale : les quatre axes se résolvent depuis lui** (36 champs, schéma identique sur les quatre profils fournis) |
| `repo-context/` | cite la preuve de l'axe Harness ; détection des boucles (§ 02) |
| `sonar-measures.json` | note de prérequis qualité, hors calcul |
| `declaratif.md` | hors calcul ; mesure l'écart dit / constaté, sert le plan de progression |
| `pull-requests.json` | inventorié (cohérence `available`), **hors calcul** : ses champs ne se comparent à aucune médiane (spec 03, 2026-08-29) |
| `code/` · `session.md` | non lus par le calcul |

Trois avertissements de la source à respecter : les autres profils n'auront pas les mêmes
trous ; les outils diffèrent (« ce qui compte, c'est ce qui est en place, jamais la marque » —
**détecter `.claude/` serait une faute**) ; ne pas croire le déclaratif, ne pas s'arrêter aux
métriques, ne pas confondre richesse et niveau.

## 4. Architecture — hexagonale, trois couches, un seul domaine

Stack : **PHP 8.5**, `symfony/console ^7.4` (LTS), PHPUnit 13, Composer 2.10, image
`php:8.5-cli-alpine`. Versions revérifiées le 2026-08-28 (Packagist, endoflife.date).
⚠️ PHP local est 8.3.6 : **tout l'outillage tourne dans Docker**, y compris pour le
développement (PHPUnit 13 exige ≥ 8.4.1).

```
src/
  Domain/         calcul pur — aucune dépendance vers les deux autres couches
  Application/    le use case EvaluateProfile et son handler
  Infrastructure/ adaptateur dossier, lecteurs de pièces, commande console, rendu texte
```

Namespace racine : `AiddLevel\`. Code, identifiants et commentaires en anglais ; README et
docs en français ; **et tout texte destiné à l'utilisateur en français** — claims des
`Evidence`, textes des `Note`, gestes des `Recommendation`, messages de `ProfileNotAssessable`,
rendu : c'est la sortie que lit le jury, et la spec 06 en fixe le vocabulaire (« après coup,
sur la majorité », « aux étapes clés »…). Les identifiants de pointeurs (`git-activity.json ›
pull_requests.total`) restent tels quels. Précisé le 2026-08-29 (remarque Codex sur la PR
#18).

### 4.1 Domaine — les types

| Type | Rôle |
|---|---|
| `Level` (enum) | les sept niveaux avec `rank()` et `label()` (icône + nom) |
| `Axis` (enum) | `Size`, `Harness`, `Intervention`, `Parallelism` |
| `Profile` | l'agrégat lu : identité, `GitActivity`, pièces optionnelles (`RepoContext`, `SonarMeasures`, `Declarative`, `PullRequests`) — chacune nullable |
| `Evidence` | une affirmation **et son pointeur** (fichier, champ, valeur). Une preuve sans pointeur ne se construit pas (règle 4 de § 06) |
| `AxisVerdict` | axe, niveau atteint, confiance (`Confirmed` / `Range(floor, ceiling, missing)`), preuves |
| `AxisEvaluator` (interface) | **le point d'extension** : `evaluate(Profile): AxisVerdict`. Un évaluateur par axe. Ajouter un axe = une classe, le calcul du minimum ne change pas |
| `LevelRule` | le minimum des verdicts → `Assessment` ; identifie l'axe (ou les axes ex æquo) qui plafonne |
| `Assessment` | statut (`Evaluated` / `LowConfidence` / `NotAssessable`), niveau, axes plafonnants, verdicts, `Recommendation`, notes |
| `Recommendation` | le geste vers le cran suivant, ordonné selon § 06 |
| `ProfileSource` (port) | `load(string $path): Profile` — la seule frontière d'entrée |
| `*Thresholds` (constantes) | `SizeThresholds`, `InterventionThresholds`, `ParallelismThresholds`, `SampleFloors` — chaque constante porte en commentaire son origine ou « adaptation assumée » |

### 4.2 Application

`EvaluateProfile` (requête : chemin) → `EvaluateProfileHandler` : gate (§ 05) →
évaluateurs → `LevelRule` → `Assessment`. Aucune exception ne remonte à l'utilisateur :
un échec de gate devient un `Assessment` de statut `NotAssessable`.

### 4.3 Infrastructure

`DirectoryProfileSource` (implémente le port, lit JSON et Markdown), `EvaluateCommand`
(`symfony/console`), `TextRenderer` (§ 06). Un format d'entrée inattendu coûte un adaptateur,
rien d'autre ne bouge.

### 4.4 Ce qui est écarté, et pourquoi

Pas de persistance, pas de séparation commande/query, pas d'interface par entité, pas de port
LLM, pas d'adaptateur git : chacun n'aurait aucune seconde implémentation, et une abstraction
vide se paie sur le critère « la qualité est là ? ».

## 5. Règles non négociables

1. `Domain/` n'importe jamais `Application/` ni `Infrastructure/` (gardé par hook, § 08).
2. Tout seuil vit dans une constante nommée avec sa justification à côté.
3. Toute ligne d'explication porte un pointeur vérifiable.
4. Le déclaratif n'entre dans aucun calcul.
5. La médiane décide, jamais le maximum (« un pic isolé ne compte pas »).
6. Un profil incomplet ne fait jamais planter : il sort un statut.
7. Un commit ne touche pas domaine et infrastructure ensemble (gardé par hook).

## 6. Interface utilisateur

```
bin/aidd-level evaluate <dossier-profil> [<dossier-profil>...]
```

Un profil qui échoue au gate n'arrête pas l'évaluation des autres. Sans argument, la commande
liste les profils livrés dans `profiles/`.

**Lancement de référence — arbitré par Jonathan le 2026-08-29** (remplace `docker build` +
`docker run`, jugé moins lisible qu'un conteneur qui tourne) : un conteneur de développement
qui reste vivant, piloté par `make` :

| Commande | Derrière |
|---|---|
| `make up` | `docker compose up -d --build` **puis** `docker compose exec php composer install` — le montage `.:/app` cache le `vendor/` construit dans l'image, les dépendances s'installent donc dans l'espace de travail monté (remarque Codex, PR #30) ; le service `php` reste vivant (`command: sleep infinity`) |
| `make demo` | les quatre profils fournis puis `profiles/self` |
| `make exec` | `docker compose exec php sh` — **on entre dans le conteneur** |
| `make evaluate arthur` | **tapée dans le conteneur** : `bin/aidd-level evaluate profiles/arthur`, sans Docker — le **nom du profil** suffit (résolu dans `profiles/`, puis `fixtures/`, puis pris comme chemin) ; plusieurs noms acceptés. `make` est installé dans l'image ; tapée hors du conteneur, la cible passe par `docker compose exec` pour que ça marche aussi |
| `make down` | `docker compose down` |
| `make test` · `lint` · `dup` · `fmt` | `docker compose exec php …` (le conteneur doit tourner ; message clair sinon) |

Notice du README, deux lignes : `make up` une fois, puis `make evaluate <profil>` — la même
commande se relaie d'elle-même dans le conteneur quand elle est tapée dehors. Pas de repli
sans `make` dans le README (arbitré par Jonathan le 2026-08-31 : moins de chemins, un README
plus court) ; le `Dockerfile` garde son `ENTRYPOINT`, l'image autonome reste possible sans
être documentée là.

Les quatre profils fournis sont recopiés dans `profiles/` avec attribution MIT
(`profiles/ATTRIBUTION.md` → `ai-driven-dev/laivel-up`, commit `89b9e35`).

## 7. Livrables notés, hors code

Livrables inchangés : `README.md`, `docs/methode.md` (une page : ce qu'on mesure, pourquoi, le
flow suivi ; cite le framework AIDD examiné et écarté, les conventions GenAI OpenTelemetry
connues et écartées, et le seul geste manuel — l'activation de la revue Codex),
`docs/calibration.md`, `docs/harness.md`, `ROADMAP.md`, `docs/journal.md`, une vidéo courte
(une minute environ, son libre, texte lisible sans le son — arbitré par Jonathan le
2026-08-31 ; minutage : `docs/scenario-video.md`).

**Amendé le 2026-08-30 — chantier 19, issue #56, périmètre élargi par Jonathan le même jour.**
L'issue prévoyait deux panneaux (`.claude/README.md`, `src/README.md`) ; le périmètre élargi
demande **la documentation de chaque partie du projet en fichiers séparés et simples**, un
**README réduit à trois choses**, et une **règle de maintenance**. Les § 7.1 à 7.7 tranchent
cela, réponses de Jonathan du 2026-08-30 comprises : le bloc de renvois du README, la table des
commandes déplacée, l'emplacement de `docs/sortie.md`, le plafond d'une page et l'unique
inscription de la règle de maintenance sont **décidés** et portés par les § qui les appliquent.
Aucune décision de scoring, aucun seuil de domaine, aucun code : ce chantier ne touche ni
`src/*.php`, ni `tests/`, ni `fixtures/` — la règle 7 d'`AGENTS.md` (domaine et infrastructure
dans un même commit) est sans objet.

### 7.1 Quatre registres, un seul endroit par chose

La source unique de la mémoire projet est `AGENTS.md` (spec 08 § 12.1) ; la source unique d'une
décision est sa spec (`AGENTS.md`, « les spécifications font foi »). La documentation ajoutée
ici ne crée pas une troisième source : elle **décrit ce qui existe et renvoie**.

| Registre | Fichiers | Ce qu'il porte |
|---|---|---|
| **décide** | `docs/specs/` | règles, seuils et leur origine, cas dégradés, tests |
| **mémoire des agents** | `AGENTS.md`, `CLAUDE.md` (artefacts Claude Code seuls) | ce qu'un agent doit savoir avant d'agir |
| **décrit ce qui existe** | les **quatre panneaux** (§ 7.2) | qui fait quoi, où ça vit, vers quelle spec aller |
| **raconte** | `docs/methode.md`, `docs/harness.md`, `docs/calibration.md`, `ROADMAP.md`, `docs/journal.md` | les choix et ce qui a été écarté, ce qui a réellement tourné, les preuves, l'historique |

**Trois interdits, valables pour les quatre panneaux et pour le `README.md`** — c'est la
condition « zéro duplication » de l'issue #56 rendue vérifiable (§ 7.7, test 2) :

1. **aucune valeur de seuil** (ni bande de taille, ni plancher d'échantillon, ni `REVIEW_WAIT_MAX`,
   ni `MAX_CONCURRENT_FRONTS`…) : un panneau **nomme** la constante et **dit où elle vit**, il ne
   la chiffre pas ;
2. **aucune règle non négociable ni point de revue recopié** d'`AGENTS.md` : le panneau renvoie au
   § d'`AGENTS.md` ;
3. **aucun tableau recopié** d'une spec ou d'`AGENTS.md`.

Un panneau qui n'a que des pointeurs à offrir sur un sujet ne l'ouvre pas.

**Emplacement : le panneau est à la racine de ce qu'il décrit.** Motif, et c'est aussi ce qui
rend la règle de maintenance mécanique (§ 7.5) : le lecteur — humain qui ouvre le répertoire,
agent qui liste `src/` avant d'éditer — trouve le panneau sans le chercher, et la question « quel
fichier de doc ce diff rend-il faux ? » se répond par le chemin touché. Les deux panneaux de
l'issue #56 sont donc **gardés tels quels, nom et place compris** (`.claude/README.md`,
`src/README.md`) ; le périmètre élargi en ajoute deux, sans en fusionner aucun : un panneau, un
sujet.

### 7.2 Les quatre panneaux

Chacun tient en **une page** (plafond et mesure : § 7.7, test 1), en français, et ne contient
**aucun extrait de code source** — une sortie de l'outil copiée telle quelle n'est pas du code,
c'est une pièce à conviction, et `docs/sortie.md` en porte une.

| Panneau | Sujet |
|---|---|
| `.claude/README.md` | l'architecture IA — le harnais |
| `src/README.md` | l'architecture du projet — hexagonale, trois couches |
| `profiles/README.md` | les entrées — ce que l'outil lit |
| `docs/sortie.md` | les sorties — ce que l'outil rend |

**`.claude/README.md` — le harnais.** Contenu, vérifié le 2026-08-30 contre
`.claude/settings.json` et l'arborescence de `.claude/` (six événements câblés sur cinq
scripts, plus deux scripts qui ne sont câblés sur aucun événement) :

- les **trois agents** (`spec`, `dev`, `front`) : une ligne chacun — ce qu'il fait, et s'il
  travaille en worktree. **Ni modèle, ni effort, ni `maxTurns`** : ce sont des valeurs, elles
  vivent en spec 08 § 2 et § 11.5 ;
- les **trois skills** (`bootstrap`, `feature`, `roadmap`) : ce que chacun déclenche ;
- **tout ce que contient `.claude/hooks/`**, en deux groupes que le panneau ne mélange pas :
  - les **cinq scripts câblés sur un événement** (`guard-layers.js`, `guard-git.js`,
    `format.js`, `journal.js`, `roadmap-ready.js`) avec l'événement qui les appelle ;
  - les **deux scripts câblés sur aucun événement**, sans quoi le panneau serait faux au sens du
    § 7.5 : **`feature-lock.js`**, l'utilitaire de verrou par issue — appelé en ligne de commande
    au premier et au dernier geste du skill `feature`, et c'est le verrou qu'il pose que
    `guard-git.js` exige pour laisser passer un `gh pr create` (spec 08 § 3) — et `lib.js`,
    la bibliothèque partagée. `.claude/hooks/tests/` est cité pour ce qu'il est ;

  La **règle** exacte de chaque hook et le détail du verrou restent en spec 08 § 3 et § 4 ;
- le **flow d'une PR en cinq lignes** : issue → `/feature <n°>` → PR + label `to-review` →
  revue Codex et une passe de correction → `gh pr merge --auto`. Les garanties détaillées et
  l'ordre exact restent `AGENTS.md § Flow d'une PR` ;
- les **trois mots** de pilotage de la roadmap, nommés ; leur effet est dans `AGENTS.md`.

Pointeurs : `docs/specs/08-harnais.md`, `AGENTS.md § Flow d'une PR`, `docs/harness.md`.
Frontière avec `docs/methode.md` (« comment ça a été construit ») : la méthode raconte les choix
et ce qui a été écarté, le panneau liste les artefacts présents aujourd'hui.

**`src/README.md` — l'architecture du projet.**

- les **trois couches** et le **sens** des dépendances, en une figure : `Infrastructure` →
  `Application` → `Domain`, et `Domain` n'importe rien des deux autres (règle 1 d'`AGENTS.md`,
  tenue par le hook `guard-layers`) ;
- **où vivent les seuils** : `src/Domain/Threshold/` **et** `src/Domain/Axis/Harness/LoopThresholds.php`
  (vérifié le 2026-08-30 : ce sont les deux seuls emplacements), une constante nommée par seuil,
  son origine en commentaire à côté. **Aucune valeur n'est reproduite dans le panneau** ;
- le **point d'extension** : `AxisEvaluator` — un évaluateur par axe, ajouter un axe est une
  classe, la règle du minimum ne bouge pas ;
- **le chemin d'un appel**, en une ligne : `bin/aidd-level` → `ApplicationFactory` →
  `EvaluateProfile` / `EvaluateProfileHandler` → les évaluateurs → `LevelRule` → `TextRenderer` ;
- **où sont les tests qui figent les décisions** : `tests/` (un test par décision de scoring),
  `tests/Calibration/`, `tests/expected/`, `fixtures/`.

Pointeurs : § 4 de cette spec (les types), specs 01 à 04 (un axe chacune), 05 (robustesse),
06 (sortie).

**`profiles/README.md` — les entrées.**

- ce qu'est un profil : **un dossier de mesures pré-agrégées**, jamais un dépôt git à cloner ;
- les **huit pièces possibles**, nommées, et pour chacune : lue ou non lue par le calcul. Le
  *pourquoi* de chaque exclusion reste au § 3 de cette spec et en spec 03 ;
- **ce que contient ce dépôt** : les six profils (quatre calibrés, `venec` et `lancelot` sans
  niveau attribué), `profiles/self/` fabriqué par `scripts/self-profile.py`, et
  `profiles/ATTRIBUTION.md` pour la licence ;
- **où les champs sont lus** : `src/Infrastructure/Profile/`, **un lecteur dédié par mesure
  parsée** — `GitActivityReader`, `PullRequestsReader`, `RepoContextReader`,
  `SonarMeasuresReader` (`JsonFile` n'étant que le décodage). Le reste est à
  `DirectoryProfileSource`, et le panneau le dit tel quel : il lit `profile.json` lui-même
  (identité et `available`), applique le gate, **note la seule présence** de `declaratif.md`, et
  **inventorie** `code/` et `session.md` sans jamais les ouvrir. Vérifié le 2026-08-30 dans
  `src/Infrastructure/Profile/` : quatre lecteurs, pas huit — « un lecteur par pièce » serait
  faux ;
- **`fixtures/`** : les dossiers fabriqués pour les cas que les profils fournis ne prouvent pas ;
- que **champ absent ≠ zéro** — nommé, jamais re-justifié : spec 05 § *Signal absent*.

Le § 3 de cette spec cite `profiles/README.md` comme source de la liste des huit pièces : c'est
le fichier **du sujet**, absent d'ici (vérifié le 2026-08-30 — `profiles/` ne contient que
`ATTRIBUTION.md` et les dossiers de profils). Le panneau créé porte le même nom et la même
liste : le pointeur du § 3 cesse d'être pendant, sans que le § 3 soit réécrit.

**`docs/sortie.md` — les sorties.**

- **la sortie réelle** d'un profil fourni, copiée telle quelle et **annotée bloc par bloc** :
  l'en-tête (niveau, frise, fiabilité, niveau suivant et sa condition de passage), « Ce qui a
  mené là », « Déjà acquis pour X », « Comment monter d'un cran », « Notes » ;
- **les trois statuts** — `évalué`, `évalué, confiance basse`, `non évaluable` — et ce que
  l'utilisateur voit dans chacun ; chacun a son rendu figé dans `tests/expected/`
  (`evaluated.txt`, `low-confidence.txt`, `not-assessable.txt`, vérifié le 2026-08-30) ;
- **le format de pointeur** `fichier › champ = valeur`, et le fait qu'aucune ligne ne sort sans
  (règle 3 d'`AGENTS.md`, pointée) ;
- **ce que l'outil n'imprime jamais** : pas de `--json`, aucun jugement de qualité, aucun
  contenu déclaratif.

Pointeurs : specs 06 (format et table des gestes) et 05 (statuts),
`src/Infrastructure/Render/TextRenderer.php`, `tests/expected/`.

Emplacement, tranché le 2026-08-30 : `docs/sortie.md`, et non un panneau co-localisé sous
`bin/` ou `src/Infrastructure/Render/`. Motif : la sortie **n'a pas de répertoire à elle** —
elle est produite par `src/Infrastructure/Render/` et lue par l'utilisateur ; un panneau enterré
à cet endroit ne serait ouvert par personne, alors que le lecteur cherche déjà `docs/methode.md`.
C'est la seule exception à la co-location du § 7.1, et elle est nommée comme telle : la table du
§ 7.5 lui donne explicitement ses chemins déclencheurs, puisque son nom ne les dit pas.

### 7.3 Le `README.md`, réduit à trois choses

Décision de Jonathan (2026-08-30) : **installation, commandes, explication de la sortie. Rien
d'autre.** Compatible avec la source (brief, table des livrables : « notice en trois lignes —
installer, lancer, sortie obtenue — puis le détail dessous ») et avec le critère qu'elle sert,
« lançable depuis ton README » : ce qui change, c'est que **le détail n'est plus dessous, il est
dans les panneaux** — le README les pointe.

Le README porte donc, dans cet ordre : le titre et **une** phrase disant ce que fait l'outil ;
puis

1. **Installation et lancement** — la notice en deux lignes du § 6 (`make up`, puis
   `make evaluate <profil>`) ; pas de repli sans `make` (§ 6, arbitré le 2026-08-31) ;
2. **Commandes** — la table des cibles `make` (§ 6) ;
3. **La sortie** — la **sortie réelle complète** d'un profil, recopiée telle quelle depuis une
   exécution (arbitré par Jonathan le 2026-08-31 : elle se lit d'elle-même, aucune liste de
   blocs à côté) ;
4. un bloc final de renvois, **une ligne par panneau** (§ 7.2) — tranché le 2026-08-30 : il est
   gardé, et il est **de la navigation, pas du contenu**. Une ligne de renvoi ne dit que le nom
   du panneau et son sujet ; dès qu'elle explique quelque chose, elle est du contenu et redescend
   dans le panneau.

**Le README montre, les panneaux définissent.** Conséquences, qui sont ce que « rien d'autre »
veut dire ici :

- la sortie réelle vit à deux endroits pour deux lecteurs : **brute dans le README** (on la
  regarde), **commentée dans `docs/sortie.md`** (on la comprend) — ce sont deux usages, pas une
  duplication de prose ;
- le README **ne définit ni les trois statuts, ni le format de pointeur, ni la règle du
  minimum** : il les laisse à `docs/sortie.md` et aux specs ;
- les sections actuelles « Ce que fait l'outil », « Comment fonctionne le calcul », « Exemple de
  sortie réelle », « Structure du dépôt », « Attribution des profils », « En construction » et
  « L'outil sur son propre dépôt » **disparaissent du README** : leur contenu part où le dit le
  § 7.4 ;
- le bloc de renvois (point 4) est **de la navigation, pas du contenu** : sans lui le jeu de
  documentation serait injoignable depuis le seul fichier que le jury ouvre à coup sûr.

**La table des commandes `make` vit dans le `README.md`, une seule fois** (tranché le
2026-08-30). `AGENTS.md § Stack et
commandes` garde la stack et la phrase « les commandes du projet passent par `make` (Docker) ;
ne pas lancer `php`, `composer` ni `vendor/bin/*` directement », et **remplace sa table par un
renvoi** dont la chaîne est fixée ici parce qu'un test la cherche : la section porte
« table des cibles : `README.md` § Commandes » — d'où le titre **`## Commandes`** dans le
README (§ 7.3, point 2). Motif : deux tables de commandes, c'est exactement la duplication que
08 § 12.1 interdit ; et entre les deux, celle qui doit rester est celle du livrable noté, que le
jury lit — un agent, lui, ouvre le fichier qu'on lui pointe. C'est le **seul contenu**
d'`AGENTS.md` que ce chantier déplace ; son autre modification, le point de revue 10 (§ 7.5),
est un ajout. Ce qui le fige : le contrôle 2b du § 7.7 (plus aucune ligne de table `| \`make …`
dans `AGENTS.md`, et un renvoi au README). Cas dégradé, nommé pour ne pas se rejouer : une cible
`make` ajoutée plus tard s'écrit **dans le README seul** ; si elle n'intéresse que les agents,
elle reste une phrase d'`AGENTS.md`, jamais une seconde table.

### 7.4 Où va ce que le README perd

Rien ne se perd, rien ne se recopie.

| Section actuelle de `README.md` | Destination |
|---|---|
| « Ce que fait l'outil », 1er § (dossier de profil, ce qui est rendu) | la phrase d'introduction du README, plus `profiles/README.md` (ce qui est lu) et `docs/sortie.md` (ce qui est rendu) |
| « Ce que fait l'outil », 2e § (la grille en une phrase, le minimum) | `docs/sortie.md` — **nommé, pas re-justifié** : la règle est § 2 de cette spec et `AGENTS.md` |
| « Comment fonctionne le calcul », points 1 à 3 (un axe par évaluateur, le minimum, la médiane) | `src/README.md` — l'ossature ; les seuils et leur origine restent en specs 01 à 04 |
| « Comment fonctionne le calcul », points 4 et 5 (signal absent, trois statuts) | `docs/sortie.md` |
| « Exemple de sortie réelle » (bloc complet) | `docs/sortie.md`, annoté |
| « Structure du dépôt » (arborescence) | **supprimée sans remplacement** : chaque panneau décrit son répertoire, et le bloc de renvois du README en tient lieu de carte |
| « Commandes `make` » | reste dans le `README.md` (§ 7.3), et y devient l'unique table |
| « Attribution des profils » | `profiles/README.md`, qui renvoie à `profiles/ATTRIBUTION.md` |
| « En construction » (hackathon, pointeurs specs / calibration / roadmap) | **supprimée sans remplacement** : `docs/methode.md` § « Comment ça a été construit » et `ROADMAP.md` la portent déjà |
| « L'outil sur son propre dépôt » | **supprimée sans remplacement** : `docs/methode.md` § « Ce que l'outil dit de son propre dépôt » la porte déjà, en plus complet ; `profiles/README.md` cite `profiles/self/` en une ligne |

### 7.5 Règle de maintenance : la doc bouge dans la PR qui la rend fausse

Demande de Jonathan (2026-08-30) : **toute modification du projet met à jour les fichiers de
documentation concernés au fur et à mesure.** Formulée pour être vérifiable :

> Une PR qui rend un panneau **faux** le corrige **dans la même PR**. Un panneau est faux quand
> il nomme un artefact qui n'existe plus, en omet un qui existe, ou décrit un chemin, une couche
> ou un flux qui a changé. Une correction qui ne rend aucun panneau faux (typo, refactor interne,
> test ajouté) n'oblige à rien.

Quel panneau, pour quel chemin :

| Ce que la PR touche | Panneau à revoir |
|---|---|
| `.claude/**` (agents, skills, hooks, `settings.json`) | `.claude/README.md` |
| `src/**` (couches, types, emplacement des seuils, point d'extension) | `src/README.md` |
| `profiles/**`, `fixtures/**`, `src/Infrastructure/Profile/**`, `scripts/self-profile.py` | `profiles/README.md` |
| `src/Infrastructure/Render/**`, `src/Domain/Progression/**`, les statuts, `tests/expected/**` | `docs/sortie.md` **et** `README.md` |
| `Makefile`, `Dockerfile`, `compose.yaml`, `bin/**` | `README.md` |

La quatrième ligne vise **deux** fichiers, et c'est la seule : le `README.md` embarque le
bloc d'en-tête entier d'une sortie réelle (§ 7.3, amendé par docs/specs/06-sortie-et-progression.md
§ 11). Une forme de sortie qui change périme donc `docs/sortie.md` **et** cet extrait — les
corriger séparément ferait mentir le `README.md` en suivant la table à la lettre.

**Où la règle est écrite : une seule fois, dans `AGENTS.md` › Code Review Rules, en point 10**
(les points 1 à 9 sont inchangés et cités ailleurs — 08 § 12.1 : on ajoute à la fin, on ne
renumérote pas) :

> 10. **La doc suit le code** : une PR qui rend faux un panneau de documentation
>     (`.claude/README.md`, `src/README.md`, `profiles/README.md`, `docs/sortie.md`,
>     `README.md`) le corrige dans la même PR ; la table chemin → panneau est en
>     `docs/specs/00-vue-ensemble.md` § 7.5.

Pourquoi là et **nulle part ailleurs** : la règle se constate sur un diff, et la liste des
points de revue est justement la liste que le reviewer parcourt sur chaque diff — l'écrire aussi
en Conventions ou en Définition de fini serait la duplication interne qu'`AGENTS.md` s'interdit
(08 § 12.1). Les agents `dev` et `front` lisent `AGENTS.md` en entier, points de revue compris :
la règle leur parvient avant la revue.

**Pas de hook, pas de job de CI.** Un contrôle mécanique « `.claude/` touché sans
`.claude/README.md` » se déclencherait sur chaque correction de typo : ce serait un hook qu'on
apprend à contourner, et « un hook qui ne se déclenche pas au test est retiré » (08 § 4) ne
protège pas contre l'inverse, un hook qui se déclenche à tort. La règle reste donc un **point de
revue**, comme le point 9. Si elle est prise en défaut — un panneau faux mergé —, le constat va
au journal avec le pointeur de la PR, et **la bascule vers un contrôle mécanique devient un
chantier à part** (même escalade que 08 § 12.5, test 3).

### 7.6 Sorties du chantier 19

| Fichier | Rôle |
|---|---|
| `.claude/README.md` | **créé** — panneau du harnais (§ 7.2) |
| `src/README.md` | **créé** — panneau de l'architecture (§ 7.2) |
| `profiles/README.md` | **créé** — panneau des entrées (§ 7.2) |
| `docs/sortie.md` | **créé** — panneau des sorties (§ 7.2) |
| `README.md` | **réduit** aux trois choses du § 7.3, plus le bloc de renvois |
| `AGENTS.md` | deux modifications, et deux seulement : point de revue **10** (§ 7.5) ; § Stack et commandes, table remplacée par un renvoi au README (§ 7.3) |
| `docs/specs/00-vue-ensemble.md` | ce § 7 |
| `docs/specs/08-harnais.md` § 12.1 | **une ligne** de renvoi : les panneaux relèvent du § 7 de la spec 00 et pointent `AGENTS.md` au lieu de le recopier |
| `docs/specs/07-amorcage.md` | **une ligne** de renvoi : le squelette de `README.md` du jour 1 est l'amorçage, pas la cible (le texte du jour 1 n'est pas réécrit) |
| `profiles/self/` | **régénéré** en fin de chantier (`python3 scripts/self-profile.py`), commit `chore(self)` séparé — `repo-context/` y recopie l'`AGENTS.md` modifié |
| `docs/journal.md` | une ligne : « arbitrages intégrés à `docs/specs/00-vue-ensemble.md` », pointeur = l'issue #56, où les réponses ont été données (08 § 12.2) |
| `ROADMAP.md` | une ligne ajoutée (append-only), chantier **19**, périmètre élargi et sorties ci-dessus |

Ligne à ajouter à `ROADMAP.md`, colonnes de la table :

```
| 19 | Documentation par partie — quatre panneaux, README réduit à trois choses, la doc suit le code | 00 § 7 (2026-08-30) | 18 | `.claude/README.md`, `src/README.md`, `profiles/README.md`, `docs/sortie.md`, `README.md`, `AGENTS.md`, `docs/specs/00-vue-ensemble.md`, `docs/specs/07-amorcage.md`, `docs/specs/08-harnais.md`, `profiles/self/` | #56 | spec écrite |
```

Deux exigences sur cette cellule, et elles viennent du code qui la lit — `outputsOverlap` de
`.claude/hooks/roadmap-ready.js` ne rapproche deux sorties que par **égalité** ou par **parent
délimité par `/`** (vérifié le 2026-08-30, `na === nb || na.startsWith(nb + '/') ||
nb.startsWith(na + '/')`) :

- **les noms de fichiers sont entiers** : `docs/specs/00` ne recouvrirait pas
  `docs/specs/00-vue-ensemble.md` et un chantier concurrent qui touche cette spec ne serait pas
  écarté. D'où les trois noms complets ;
- **`profiles/self/` y figure**, puisque ce chantier le régénère : sans lui, un front qui
  refabrique les profils pourrait partir en même temps.

La première ligne du chantier 19 déclarait deux sorties (`.claude/README.md`, `src/README.md`) ;
celle-ci les remplace par la liste complète. C'est nécessaire, pas cosmétique : la condition 5
du § 11.3 de la spec 08 lit **la dernière ligne qui déclare des sorties**.

`profiles/self/repo-context/` recopie `AGENTS.md` : ce chantier le modifie, donc `profiles/self/`
est régénéré en fin de chantier comme le veut `docs/methode.md`, dans un commit `chore(self)`
séparé, et l'épreuve 7 du § 12.5 de la spec 08 s'applique telle quelle.

### 7.7 Tests / épreuve

Aucun test PHPUnit : ce chantier ne touche pas `src/`. Les contrôles ci-dessous se tapent à la
main dans la PR ; leur résultat va au journal. `make test`, `make lint`, `make dup` et
`make demo` doivent rester verts et `docs/calibration.md` vrai — inchangés par construction.

1. **Une page par fichier.** Pour chacun des cinq fichiers (`README.md` et les quatre panneaux) :
   lignes non vides **hors blocs de code délimités par ```**, plafond `60`.

   ```
   awk '/^```/{f=!f; next} !f && NF' <fichier> | wc -l
   ```

   Origine du plafond : « une page maximum » (issue #56) ; **60** lignes non vides est une
   **adaptation assumée** (une page A4 rendue depuis du Markdown), validée par Jonathan le
   2026-08-30 et **révisable au journal, jamais en silence**. Les blocs de code sont exclus
   parce qu'une sortie de l'outil copiée telle quelle n'est pas de la prose : c'est la pièce à
   conviction, et la tronquer pour tenir un plafond la rendrait fausse.

   Cas dégradé — **un dépassement ne relève pas le plafond dans la PR** : il dit qu'un contenu
   est écrit du mauvais côté. Deux issues, dans cet ordre : le passage décide quelque chose, il
   remonte dans la spec ou dans `AGENTS.md` et le panneau le pointe ; ou il décrit un artefact de
   plus, et c'est le signe qu'un panneau doit être coupé en deux — ce qui est alors un chantier,
   pas une retouche. Relever `60` se journalise avec le fichier et le compte constaté.
2. **Zéro duplication.** Comparaison mécanique des lignes normalisées, **paire par paire**,
   entre les cinq fichiers, et entre chaque panneau et `AGENTS.md`, `docs/methode.md` et les
   specs qu'il cite :

   ```
   prose() { awk '/^```/{f=!f; next} !f' "$1"; }          # même exclusion qu'au test 1
   norm() { prose "$1" \
            | sed -E 's/^[[:space:]]*[-*>|][[:space:]]*//; s/[`*_#|]//g; s/[[:punct:]]+/ /g; \
                      s/[[:space:]]+/ /g; s/^ //; s/ $//' | tr 'A-Z' 'a-z' \
            | awk 'NF>=5' | sort -u; }
   comm -12 <(norm A) <(norm B)
   ```

   Sortie attendue : **vide**. **Le test ne juge que la prose** : les blocs délimités par ```
   sont retirés avant normalisation, exactement comme au test 1. Sans cette exclusion le test
   serait insatisfaisable par construction — le § 7.3 met le **bloc d'en-tête entier** d'une
   sortie réelle dans le `README.md` et le § 7.2 la **même** sortie, complète, dans
   `docs/sortie.md` : `Fiabilité : évalué — …` et `Niveau suivant : …` seraient comptées comme
   des lignes dupliquées alors qu'elles sont la pièce à conviction, citée deux fois **exprès**.
   La contrepartie est écrite : l'extrait du `README.md` est un **préfixe littéral** de la
   sortie de `docs/sortie.md`, jamais une variante — une divergence entre les deux se voit à
   l'œil et relève du test 5 et du point de revue 10, pas de ce `comm`.

   Le filtre `NF>=5` (adaptation assumée) écarte les titres et les en-têtes de table, qui se
   ressemblent sans rien dupliquer ; en dessous de cinq mots, une ligne n'énonce pas une règle.
   Le test attrape le copier-coller, pas la paraphrase — la paraphrase reste du ressort de la
   revue (points 8 et 9).

   **Jetons de contrôle**, chacun présent dans **exactement un** des cinq fichiers — ils prouvent
   que ce qui a quitté le `README.md` (§ 7.4) y a bien été retiré, et qu'il a atterri à un seul
   endroit : `guard-git` → `.claude/README.md` ; `AxisEvaluator` → `src/README.md` ;
   `ATTRIBUTION.md` → `profiles/README.md` ; `confiance basse` → `docs/sortie.md` ; `make up` →
   `README.md`. Et `Structure du dépôt`, présent **nulle part**. Ces jetons ne portent que sur
   les cinq fichiers : un nom d'artefact ou de classe apparaît légitimement aussi dans `AGENTS.md`
   et dans les specs, c'est un nom, pas une règle qui se répète (08 § 12.5, test 2).

   **2b. Une seule table de commandes** (§ 7.3). Les deux moitiés se lisent **dans la seule
   section `## Stack et commandes`** — la chercher dans tout `AGENTS.md` ne prouverait rien, le
   point de revue 10 y cite déjà `README.md` :

   ```
   section() { sed -n '/^## Stack et commandes$/,/^## /p' AGENTS.md; }
   section | grep -n '^| `make '                  # attendu : vide (plus de table)
   section | grep -c 'README.md § Commandes'      # attendu : 1 (la phrase de renvoi)
   grep -c '^| `make ' README.md                  # attendu : ≥ 1 (la table y est, et là seulement)
   ```
3. **Aucun seuil chiffré dans un panneau** (interdit 1 du § 7.1). **Sur la prose seule**, blocs
   délimités par ``` exclus comme aux tests 1 et 2 (fonction `prose`, définie au test 2) :

   ```
   for f in .claude/README.md src/README.md profiles/README.md docs/sortie.md; do
     prose "$f" | grep -nE '[=≥≤><][[:space:]]*[0-9]|[0-9][[:space:]]*(%|min|h|fichiers|lignes|essais)' \
       | sed "s|^|$f:|"
   done
   ```

   Attendu : **vide**. L'exclusion n'est pas un confort : la sortie réelle copiée dans
   `docs/sortie.md` **contient** des valeurs (`median_files_changed = 29`, « plancher 5 »…) parce
   que c'est ce que l'outil imprime ; les filtrer du bloc reviendrait à falsifier la pièce. Ce
   que l'interdit 1 vise, ce sont les **seuils écrits en prose** par le panneau, seuls capables
   d'entrer en concurrence avec la constante nommée du domaine ou avec une spec. Les valeurs
   numériques de prose admises ailleurs (`PHP 8.5`, `PHPUnit 13`, `symfony/console ^7.4`) vivent
   dans le `README.md`, hors panneaux, et ne correspondent à aucun de ces motifs.

   Cas dégradé : un chiffre du bloc de sortie qui **devient faux** (la sortie a changé) n'est pas
   affaire de ce test mais de la table du § 7.5, quatrième ligne — `docs/sortie.md` **et**
   `README.md` se recopient depuis une exécution réelle, jamais à la main.
4. **Aucun pointeur pendant.** Chaque chemin cité entre accents graves dans les cinq fichiers
   existe (`test -e`), et chaque `§` cité existe dans le fichier visé. Ce test est la raison
   d'être du § 7.2 sur `profiles/README.md` : c'est exactement la classe de défaut qu'il corrige.
5. **Le dépôt reste lançable depuis son README.** Un lecteur qui n'ouvre que `README.md` va de
   l'installation à une sortie affichée sans ouvrir un autre fichier : `make up`, `make exec`,
   `make evaluate arthur`. Critère du brief, éprouvé à la main.
6. **La règle de maintenance n'est écrite qu'une fois** (§ 7.5, tranché le 2026-08-30) :
   `grep -n 'docs/specs/00-vue-ensemble.md § 7.5' AGENTS.md` → **exactement une** ligne, dans les
   Code Review Rules ; rien d'équivalent en Conventions ni en Définition de fini. Une seconde
   occurrence est la duplication interne qu'`AGENTS.md` s'interdit (08 § 12.1) : elle se retire,
   elle ne se reformule pas.
7. **Le point 10 mord.** Sur la PR du chantier suivant qui touche `.claude/` ou `src/`, la revue
   signale le panneau devenu faux, ou constate qu'il ne l'est pas. Le constat va au journal ;
   deux défauts consécutifs ouvrent le chantier « contrôle mécanique » du § 7.5.

## 8. Cohérence obligatoire

L'outil tourne sur son propre dépôt : un profil `profiles/self/` est fabriqué depuis le dépôt
rendu (compteurs de contexte, médianes des PR réelles, branches concurrentes constatées). Le
résultat se rejoue par `make self` (la vidéo courte du 2026-08-31 ne le montre plus). Un dépôt
qui se noterait Red se disqualifie seul.
