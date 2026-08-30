# 09 — Visite guidée du projet

> Spécification de **conduite**, non de calcul. Source : issue #61 « Chantier 20 — Visite guidée
> du projet », et le cadre validé par Jonathan en session le 2026-08-30 (ordre de la visite,
> traitement des écarts, spec écrite avant le début). Ce chantier ne décide rien du scoring :
> aucun seuil de domaine, aucun rendu, aucun fichier de `src/`, `tests/` ou `fixtures/`. Tous les
> chemins cités au § 4 ont été relevés dans le dépôt le **2026-08-30** ; ce qui n'a pas pu être
> vérifié est marqué **non vérifié**.

## 1. Ce que le chantier produit, et ce qu'il ne produit pas

Le chantier 20 est un **parcours commenté du dépôt en session interactive** avec Jonathan.
L'objet n'est pas de modifier le projet mais de le comprendre : chaque fichier important —
code, pièces d'entrée, sorties, harnais IA, annexes — est expliqué, discuté, et confronté à ce
que les specs et les panneaux en disent.

| Il produit | Il ne produit pas |
|---|---|
| dix étapes parcourues et journalisées (§ 6) | aucune modification de `src/`, `tests/`, `fixtures/`, des panneaux ni des specs 00 à 08 |
| une issue GitHub par écart relevé (§ 5) | aucune correction, aucun commit de correction pendant la visite |
| une ligne de `ROADMAP.md` à la clôture (§ 7) | aucun agent `dev`, aucun agent `front`, aucun worktree |

Conséquences directes, nommées pour ne pas se rejouer :

- la règle 7 d'`AGENTS.md` (domaine et infrastructure dans un même commit) est **sans objet** :
  ce chantier ne commit que `docs/journal.md`, `ROADMAP.md`, la ligne « Où en est le projet »
  d'`AGENTS.md` et ce fichier ;
- le point de revue 10 d'`AGENTS.md` (« la doc suit le code ») est **sans objet** : la visite ne
  rend aucun panneau faux, elle constate qu'il l'est déjà et ouvre une issue ;
- aucun test PHPUnit n'est ajouté. Les preuves de ce chantier sont le journal, les issues et les
  contrôles du § 9.

## 2. Qui conduit, où, avec quoi

- **Qui** : la session Claude Code, en dialogue direct avec Jonathan. Aucun sous-agent n'est
  lancé pour expliquer — un agent rendrait un rapport, or ce chantier veut une discussion.
- **Où** : le **checkout principal**, sur la branche `main`, sans worktree. Deux raisons, l'une
  et l'autre vérifiées dans le harnais le 2026-08-30 : le hook `journal.js` sort sans rien
  écrire quand la branche est `main` (`.claude/hooks/journal.js`, branche du `Stop`), donc la
  trace du journal reste celle des étapes et rien d'autre ; et `guard-git.js` ne verrouille le
  checkout principal que lorsque **plus d'un** verrou `/feature` est posé
  (`.claude/hooks/guard-git.js`, garde `locksCount(root) > 1 && isMainCheckout(root)`).
- **Avec quoi** : la lecture des fichiers, et les cibles `make` de démonstration du § 3.

**L'issue #61 ne porte aucun label** — constaté le 2026-08-30, `to-implement` compris. C'est ce
qui ferme mécaniquement la porte à `/roadmap` : la condition 1 de `roadmap-ready.js` (spec 08
§ 11.3) exige `to-implement` pour rendre un front « prêt ». Sans ce label, `/roadmap` n'ouvre
jamais ce chantier et ne lance aucun agent `front` dessus — ce qui serait contraire à sa nature,
puisqu'il n'y a rien à implémenter. Le label reste absent pendant toute la visite.

## 3. Le déroulé d'une étape

Une étape est une suite de **blocs**. Un bloc explique **un fichier**, ou un groupe de fichiers
qui n'a de sens qu'ensemble (les sept fichiers de `src/Domain/Axis/Harness/`, par exemple).

**Ordre d'un bloc**, toujours le même :

1. **ce que c'est** — le rôle du fichier dans le flux de données, en une ou deux phrases ;
2. **ce qui le décide** — la spec ou le § qui en porte la règle, cité par son chemin ;
3. **ce qu'on y voit** — le point saillant : un seuil et son origine, un cas dégradé, un
   pointeur rendu, une dépendance ;
4. **la main rendue** — une question posée à Jonathan, ou l'invitation explicite à poursuivre.

**`BLOC_MAX = 15 lignes non vides`, hors blocs de code délimités par ```**. C'est le seuil qui
rend « petits blocs, jamais de pavé » (issue #61) vérifiable. Origine : **adaptation assumée** —
un quart du plafond d'une page de documentation, `60` lignes non vides, fixé au § 7.7 de la spec
00 avec la même exclusion des blocs de code (une sortie de l'outil copiée telle quelle n'est pas
de la prose). Valeur **validée par Jonathan le 2026-08-30**, révisable au journal avec le compte
constaté, jamais en silence. Le décompte se fait comme au § 7.7 de la spec 00 :

```
awk '/^```/{f=!f; next} !f && NF' <bloc> | wc -l
```

Trois règles de conduite, chacune tenue pendant tout le bloc :

- **jamais deux blocs enchaînés sans main rendue.** Un bloc qui en appelle un autre attend la
  réponse de Jonathan ; le dépassement de `BLOC_MAX` et l'enchaînement sont le même défaut, dit
  de deux façons.
- **aucune affirmation sans pointeur.** Une explication cite `chemin:ligne` ou
  `chemin › symbole` — la même discipline que la règle 3 d'`AGENTS.md` impose au rendu de
  l'outil. Ce qui ne se pointe pas se dit **« non vérifié »**, et si un panneau ou une spec
  l'affirme quand même, c'est un écart (§ 5).
- **une question de Jonathan se traite avant de poursuivre**, avec son pointeur, dans un bloc à
  elle. Le bloc interrompu reprend ensuite là où il s'était arrêté.

**Ouverture d'une étape** : un bloc d'annonce qui liste les fichiers couverts et les blocs
prévus, pour que Jonathan puisse rediriger avant qu'on ne parte. **Clôture d'une étape** : un
bloc de relevé — ce qui a été vu, les écarts trouvés et le numéro d'issue de chacun — puis la
ligne de journal du § 6.

**Démonstrations autorisées** — celles qui ne modifient aucun fichier suivi par git :
`make up`, `make exec`, `make down`, `make evaluate <profil>`, `make demo`, `make self`,
`make test`, `make lint`, `make dup`. **`make fmt` est exclu** : il réécrit un fichier PHP.

**Interdits pendant une étape**, sans exception :

- éditer un fichier du dépôt autre que `docs/journal.md` ;
- lancer `/feature`, ouvrir une PR, pousser une branche — le hook `guard-git` refuse déjà
  `gh pr create` hors d'un run du skill ;
- régénérer `profiles/self/` ;
- corriger un écart, même d'une ligne, même évident (§ 5).

Une étape peut s'étaler sur plusieurs sessions : elle n'est journalisée qu'une fois finie, et
la reprise se lit dans la conversation, pas dans un état stocké ailleurs.

## 4. Les dix étapes

Ordre imposé par le **flux de données** — Entrées → Domain → Application → Infrastructure →
Sorties, puis tests, harnais IA, annexes (cadre validé le 2026-08-30). Une étape ne commence pas
avant que la précédente ait sa ligne de journal.

**Un fichier appartient à une étape et une seule** ; il peut être *cité* par d'autres, c'est
l'explication qui ne se répète pas. **Règle de reste** : tout fichier versionné qu'aucune étape
1 à 9 ne couvre appartient à l'étape 10 — la couverture est donc totale par construction, et le
contrôle du § 9 sert à *montrer* le reste, pas à le juger. Ce fichier, `docs/specs/09-visite-guidee.md`,
est le document de conduite : il est lu avant l'étape 1 et ne compte dans aucune étape.

| # | Partie | Fichiers et dossiers couverts (relevés le 2026-08-30) | Spec ouverte pendant l'étape |
|---|---|---|---|
| 1 | Vue d'ensemble | `README.md`, `AGENTS.md`, `CLAUDE.md`, `docs/methode.md` | `docs/specs/00-vue-ensemble.md` § 1 à 3 et § 5 |
| 2 | Entrées | `profiles/README.md`, `profiles/ATTRIBUTION.md`, les sept dossiers de profils : `arthur`, `bohort`, `leodagan`, `perceval`, `venec`, `lancelot`, `self` | `00` § 3, `05-robustesse.md` (gate, cohérence `available`) |
| 3 | Domain — socle | `src/README.md` ; `src/Domain/` à sa racine : `Level.php`, `Axis.php`, `AxisEvaluator.php`, `AxisVerdict.php`, `Evidence.php`, `Pointer.php`, `Note.php`, `Assessment.php`, `AssessmentStatus.php`, `LevelRule.php`, `LevelRuleResult.php`, `ProfileSource.php` ; `src/Domain/Confidence/`, `src/Domain/Exception/`, `src/Domain/Profile/`, `src/Domain/Threshold/` | `00` § 4.1, `05` (planchers, signal absent) |
| 4 | Domain — quatre axes | `src/Domain/Axis/Size/`, `src/Domain/Axis/Harness/` (dont `LoopDetector.php`, `LoopPatterns.php`, `LoopThresholds.php`), `src/Domain/Axis/Intervention/`, `src/Domain/Axis/Parallelism/`, `src/Domain/Axis/Support/` | `01-axe-taille.md`, `02-axe-harness.md`, `03-axe-intervention.md`, `04-axe-parallele.md` |
| 5 | Application | `src/Application/EvaluateProfile.php`, `src/Application/EvaluateProfileHandler.php` | `00` § 4.2, `05` (gate, filtre White, trois statuts) |
| 6 | Infrastructure | `src/Infrastructure/Profile/` (six fichiers, quatre lecteurs), `src/Infrastructure/Console/`, `src/Infrastructure/Render/TextRenderer.php`, `bin/aidd-level` | `00` § 4.3 et § 6, `05`, `06-sortie-et-progression.md` |
| 7 | Sorties | `docs/sortie.md`, `docs/calibration.md`, `src/Domain/Recommendation.php`, `src/Domain/Progression/`, `tests/expected/` (`evaluated.txt`, `low-confidence.txt`, `not-assessable.txt`) | `06`, `05` (statuts) |
| 8 | Tests et fixtures | `tests/` **sauf** `tests/expected/` : `tests/Domain/`, `tests/Application/`, `tests/Infrastructure/`, `tests/Calibration/`, `tests/Fixtures/` ; `fixtures/` — onze dossiers, un cas par dossier | `00` § 8, et la spec de chaque décision figée |
| 9 | Harnais IA | `.claude/README.md`, `.claude/settings.json`, `.claude/agents/` (`spec`, `dev`, `front`), `.claude/skills/` (`bootstrap`, `feature`, `roadmap`), `.claude/hooks/` (sept scripts et `tests/`), `.github/workflows/auto-merge-after-codex.yml`, `docs/harness.md`, `docs/journal.md`, `ROADMAP.md` | `08-harnais.md`, `07-amorcage.md`, `AGENTS.md § Flow d'une PR` |
| 10 | Annexes | `Makefile`, `Dockerfile`, `compose.yaml`, `composer.json`, `composer.lock`, `phpunit.xml.dist`, `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `.github/workflows/ci.yml`, `scripts/self-profile.py`, `.gitignore`, `.worktreeinclude`, **et le reste** | `00` § 6, `08` (CI et armement) |

**Lecture des cellules, et c'est ce qui rend le contrôle 5 du § 9 exécutable.** Dans la colonne
« Spec ouverte », tout nom de fichier se lit sous `docs/specs/` (`05-robustesse.md` désigne
`docs/specs/05-robustesse.md`, `00` désigne `docs/specs/00-vue-ensemble.md`). Dans la colonne des
fichiers couverts, un nom court se lit sous le **dernier répertoire nommé avant lui dans la même
cellule** (`Level.php` sous `src/Domain/`, `LoopDetector.php` sous `src/Domain/Axis/Harness/`,
`evaluated.txt` sous `tests/expected/`, `arthur` sous `profiles/`). Le contrôle 5 applique cette
résolution avant `test -e`.

Trois précisions que le tableau ne dit pas, et qui évitent une fausse piste :

- **la grille AIDD n'est pas un fichier de ce dépôt.** Sa table normative est
  `docs/specs/00-vue-ensemble.md` § 2, relevée le 2026-08-28 depuis le dépôt du sujet
  (`ai-driven-dev/laivel-up`, commit `89b9e35`, licence citée dans `profiles/ATTRIBUTION.md`).
  L'étape 1 explique la grille depuis ce §, sans jamais promettre un fichier absent ;
- **`.brief/` n'est pas versionné et n'entre dans aucune étape** (`AGENTS.md` : jamais lu par la
  CI, jamais committé, jamais cité) ;
- **`.github/workflows/` est coupé en deux exprès** : l'armement après le 👍 de Codex appartient
  au harnais (étape 9), la CI qui fait tourner tests, analyse statique et duplication appartient
  aux annexes (étape 10).

## 5. Les écarts

Un **écart** est ce que la visite constate et qui ne colle pas. Quatre familles, et elles se
nomment dans l'issue parce qu'elles n'ont pas la même suite :

| Famille | Ce que c'est |
|---|---|
| **a. code contre spec** | le code fait autre chose que ce que sa spec décide (seuil, statut, ordre, pointeur) |
| **b. documentation fausse** | un panneau ou le `README.md` nomme un artefact absent, en omet un présent, ou décrit un chemin qui a changé (définition du § 7.5 de la spec 00) |
| **c. spec contre spec** | deux specs, ou une spec et `AGENTS.md`, disent deux choses de la même règle |
| **d. manque** | une décision de scoring sans test qui la fige, un seuil sans origine, un pointeur pendant, du code mort |

**Règle, sans exception : aucune correction pendant la visite.** Un écart devient **une issue
GitHub**, ouverte au fil de l'étape (cadre validé le 2026-08-30), et il est traité plus tard par
`/feature <n°>` — la visite ne bloque pas et ne se transforme jamais en chantier de correction.
Le motif est le même que celui du § 12.2 de la spec 08 : ce qui décide se pose là où ça se
décide, pas dans le fil d'une conversation.

**Contenu minimal d'une issue d'écart** — sans quoi elle ne vaut rien plus tard :

1. le **pointeur** : `chemin:ligne` ou `chemin › champ`, et le SHA de `main` au moment du
   constat ;
2. **ce qui est constaté**, en une phrase, avec la citation exacte du dépôt ;
3. **ce que la source dit** : le § de spec, le point d'`AGENTS.md` ou le panneau contredit ;
4. la **famille** (a, b, c ou d) et l'étape de la visite qui l'a trouvé.

**Labels et roadmap — la règle, pas une option.** Une issue d'écart porte le label
`to-implement` et **ne reçoit aucune ligne dans `ROADMAP.md`**. Les deux moitiés se tiennent :
`to-implement` est le déclencheur du cycle (spec 08 § 1), et `/feature` traite lui-même le cas
« spec absente » par son arrêt humain ; l'absence de ligne de roadmap la rend invisible de
`/roadmap`, puisque la condition 2 du § 11.3 de la spec 08 lit le graphe dans `ROADMAP.md` et
qu'un cas dégradé nommé du même § (« ligne de roadmap absente pour une issue ») laisse le front
fermé, en silence. Une issue d'écart n'est donc traitable que par un `/feature <n°>` tapé
explicitement — jamais par la roadmap autonome.

**Cas dégradés :**

- **plusieurs constats, une seule cause** — une issue unique qui les liste. C'est la cause
  racine qui décide du nombre d'issues, jamais le nombre de lignes fautives ;
- **`gh` en échec, ou pas de réseau** — l'écart part quand même dans la ligne de journal de
  l'étape, avec son pointeur et la mention exacte **« issue à ouvrir (étape `<n>`) »**. À la
  reprise, l'issue est ouverte et une **nouvelle** ligne est ajoutée en fin de fichier, portant
  la mention exacte **« issue ouverte pour l'étape `<n>` : #`<n°>` »** — le fichier est
  append-only, la ligne d'origine ne se retouche pas. Ces deux formules sont littérales : le
  contrôle 4 du § 9 les compte, et un report non apparié interdit la clôture ;
- **écart bloquant** — un fichier illisible, l'outil qui ne tourne plus : l'étape se poursuit
  en lecture, les blocs de démonstration sont sautés, et l'empêchement est journalisé comme
  écart ;
- **un chemin du § 4 n'existe plus** (un chantier ultérieur l'a déplacé) : ce n'est pas un écart
  du projet, c'est ce fichier qui a vieilli. La ligne du tableau se corrige **dans la PR de
  clôture** (§ 7), et le journal le dit ;
- **une étape se révèle trop grosse pour tenir en blocs** : elle se poursuit sur plusieurs
  sessions. Elle ne se coupe pas en deux étapes — les dix étapes et leur ordre sont la seule
  chose que ce chantier promet.

## 6. La trace au journal

Une ligne de `docs/journal.md` **par étape terminée**, plus une ligne de clôture. Le journal est
exactement le bon endroit : il enregistre « ce qui n'a pas produit de commit », et une visite
n'en produit aucun.

Colonnes du fichier, remplies ainsi — **une ligne physique, jamais coupée**, ce qui suit tient
sur une seule ligne du fichier :

```
| <horodatage>Z | chantier 20 — étape <n> | session Claude Code (visite) | étape <n> « <titre> » parcourue ; <k> écart(s) : #<n°>, #<n°> | <chemins couverts> · `<sha court de main>` | <ce qui reste, ou —> |
```

Trois exigences, chacune pour une raison :

- **le SHA court de `main`** ancre l'étape à un état du dépôt : sans lui, « le fichier disait
  ceci » n'est pas rejouable ;
- **les numéros d'issue** figurent dans la ligne, ou la mention « aucun écart » ; c'est ce qui
  rend le contrôle 4 du § 9 mécanique ;
- **append-only** : une ligne d'étape ne se réécrit jamais, un complément s'ajoute en fin de
  fichier.

Les lignes s'écrivent dans `docs/journal.md` du checkout principal, sur `main`, sans commit ;
elles restent non committées jusqu'à la clôture (§ 7).

**Le journal contiendra plus que ces lignes, et la clôture ne promet pas un compte exact.**
Vérifié le 2026-08-30 dans `.claude/hooks/journal.js` : seule la branche `Stop` du hook sort sans
écrire quand la branche est `main` ; la branche `PostToolUseFailure` est traitée **avant** tout
test de branche et ajoute une ligne dès qu'une commande `make`, `docker`, `composer`, `gh pr`,
`gh api` ou un `git rebase|push|merge|cherry-pick` échoue — une démonstration ratée du § 3 en
produit une, sur `main` comme ailleurs. Ces lignes automatiques sont **gardées telles quelles**
et committées avec le reste à la clôture : elles disent ce qui s'est passé. Conséquence écrite
une fois pour toutes : le chantier ajoute **les dix lignes d'étape, la ligne de clôture, et
toute ligne produite par le hook entre-temps** ; le contrôle 1 du § 9 compte les lignes d'étape,
jamais le total du fichier.

## 7. Clôture du chantier

La clôture est le **seul** moment où ce chantier écrit dans git, et elle produit **une seule PR
`docs:` pour toute la visite** : pas de PR intermédiaire, quelle que soit la durée du parcours.
Motif : une PR par tranche d'étapes multiplierait les cycles de revue sur un contenu qui ne
décide rien, alors que les lignes de journal sont append-only et ne gênent personne tant
qu'elles restent dans le checkout principal (§ 6) — leur seul risque, une base qui vieillit, est
traité par le geste 3 et le § 7.2.

### 7.1 La route docs-only, et pourquoi ce n'est pas l'étape 2 du skill

`/feature 61` est invoqué — toute PR passe par le cycle (`AGENTS.md § Flow d'une PR`) — mais son
routage s'arrête au **chemin docs-only** : l'**étape 2 du skill (agent `dev` en worktree) n'est
jamais empruntée**. Motif, et il est technique, pas de confort : l'agent `dev` travaille dans un
worktree créé depuis `origin/main` (spec 08 § 2, `isolation: worktree`) ; le travail de clôture,
lui, est **déjà là, non committé, dans le checkout principal**. Un worktree ne le verrait pas et
rouvrirait un chantier vide. Le skill prévoit déjà cette forme : son § 3 pose que `W`, le
checkout qui a créé la PR, est « le worktree de l'agent `dev`, **ou le checkout courant** pour
une PR `docs/spec-<n°>` ou `--trivial` » — la branche `docs/visite-61` joue ici le rôle qu'y joue
`docs/spec-<n°>`, et le verrou `61` l'autorise (geste 2).

Les huit gestes, dans cet ordre, tous dans le checkout principal :

1. **Fronts en cours.** Si un front `/roadmap` est ouvert, déclarer « **pause roadmap** » et
   attendre sa fin : dès qu'un **second** verrou `/feature` existe, `guard-git.js` refuse
   `commit` et `push` dans le checkout principal (garde `locksCount(root) > 1`, § 2).
2. **Verrou.** `node .claude/hooks/feature-lock.js lock 61` — c'est lui qui autorise
   `gh pr create` sur une branche portant le numéro (`guard-git.js`, `(^|[-/])61([-/]|$)`).
3. **Base fraîche.** `git fetch origin main`, puis branche `docs/visite-61` créée sur
   `origin/main` ; les fichiers append-only modifiés dans le checkout principal y sont
   réappliqués **en fin de fichier**, jamais en réécrivant les lignes ajoutées entre-temps par un
   autre chantier (§ 7.2).
4. **Commit unique `docs:`**, portant : les lignes de journal (étapes, clôture, lignes
   automatiques du hook — § 6), la ligne d'état de `ROADMAP.md`, la mise à jour de la **ligne
   « Où en est le projet » d'`AGENTS.md`** (une ligne, pas un historique : c'est ce que ce §
   d'`AGENTS.md` exige à chaque fin de chantier) et les corrections de chemin éventuelles du § 4.
5. **PR `docs:`** avec le label `to-review` et `Closes #61`.
6. **Revue et merge** : les étapes 1 à 5 du § 3 du skill `feature` — attente du verdict Codex,
   une passe de correction menée par la session elle-même (jamais par un agent `dev`), réponse
   tracée par remarque, rebase, `gh pr merge --auto --squash --delete-branch`.
7. **Déverrouillage** : `node .claude/hooks/feature-lock.js unlock 61`, sur toute sortie —
   mergé, `blocked` ou arrêt.
8. **Reprise de la roadmap** : si le geste 1 a déclaré la pause, dire « **reprends la roadmap** »
   **après** le merge et le déverrouillage. C'est la seule façon de retirer le marqueur
   `roadmap-paused` (`AGENTS.md`, les trois mots ; spec 08 § 11.7) : une clôture qui l'oublie
   laisse la file gelée pour tous les chantiers suivants, sans que rien ne le signale.

Deux PR sur la même issue #61 (celle qui porte cette spec, celle de clôture) : c'est la forme
déjà suivie au chantier 19 sur l'issue #56 — PR #58 pour la spec, PR #59 pour les panneaux.

### 7.2 Sorties déclarées et fichiers append-only

Ligne à ajouter à `ROADMAP.md` (append-only, colonnes de la table) :

```
| 20 | Visite guidée du projet — dix étapes commentées, une issue par écart, aucun code | 09 (2026-08-30) | 19 | `docs/specs/09-visite-guidee.md`, `docs/journal.md`, `ROADMAP.md`, `AGENTS.md` | #61 | spec écrite |
```

**Les quatre fichiers touchés sont déclarés en sortie**, noms entiers, comme l'exige le § 7.6 de
la spec 00 (`outputsOverlap` de `roadmap-ready.js` ne rapproche deux sorties que par égalité ou
par parent délimité par `/`). Ne pas déclarer `docs/journal.md`, `ROADMAP.md` et `AGENTS.md`
reviendrait à contourner le garde de chevauchement de `/roadmap` — la condition 5 du § 11.3 de
la spec 08 — sur les trois fichiers que ce chantier modifie réellement. Le coût est borné : un
chantier n'est un « front ouvert » qu'à partir de son verrou ou de sa PR (§ 11.3), donc la
déclaration n'écarte les fronts concurrents que **pendant la fenêtre de la PR de clôture**, pas
pendant les dix étapes.

Cette déclaration ne suffit pas, parce que la fenêtre qu'elle couvre n'est pas celle du risque :
les lignes de journal s'accumulent pendant toute la visite, sur une base qui vieillit. D'où le
geste 3 du § 7.1, écrit comme règle : **le commit de clôture part d'`origin/main` fraîchement
récupéré et réapplique ses ajouts en fin de fichier.** Sur `docs/journal.md`, `ROADMAP.md` et la
ligne « Où en est le projet », un conflit se résout **toujours** en gardant les lignes de l'autre
chantier et en replaçant les siennes après — jamais l'inverse, ces fichiers étant append-only
(`AGENTS.md § Conventions`). Une tentative de rebase ; en cas de conflit qui résiste, arrêt,
label `blocked` et ligne de journal, comme partout ailleurs dans le dépôt.

La dépendance est **19** : les quatre panneaux mergés au chantier 19 sont la matière de
plusieurs étapes.

## 8. Ce que la visite ne promet pas

Trois limites, écrites pour qu'on ne les redécouvre pas en route :

- **elle ne valide rien.** Une étape qui ne trouve aucun écart ne prouve pas que la partie est
  juste, seulement que rien n'a été vu ce jour-là. Le journal dit « aucun écart relevé », jamais
  « partie conforme » ;
- **elle ne juge pas la qualité du code.** C'est le prérequis du sujet, pas un axe (spec 05,
  section Sonar) ; un avis de style qui ne contredit aucune spec n'est pas un écart et n'ouvre
  pas d'issue ;
- **elle ne rouvre pas les décisions figées** d'`AGENTS.md` (règle du minimum, médiane,
  déclaratif hors calcul, Gold Intervention inatteignable…). Une objection de fond se note comme
  telle dans la ligne de journal, avec son pointeur, et attend une donnée nouvelle.

## 9. Tests / épreuve

Aucun test PHPUnit : ce chantier ne touche pas `src/`. `make test`, `make lint`, `make dup` et
`make demo` doivent rester verts et `docs/calibration.md` vrai — inchangés par construction. Les
cinq contrôles ci-dessous se tapent à la main ; leur résultat va dans la ligne de journal de
clôture.

1. **Les dix étapes sont journalisées, chacune une fois.** Le compte agrégé ne suffit pas : une
   étape manquante et une étape journalisée deux fois se compensent. Les dix numéros se
   vérifient un par un, le `|` final bornant le numéro (« étape 1 | » ne se confond pas avec
   « étape 10 | ») :

   ```
   for n in $(seq 1 10); do
     printf '%2s %s\n' "$n" "$(grep -cF "chantier 20 — étape $n |" docs/journal.md)"
   done
   ```

   Attendu : dix lignes, **`1` sur chacune**. Un `0` est une étape non journalisée, un `2` une
   étape journalisée deux fois ; les deux se corrigent par une ligne ajoutée en fin de fichier
   qui dit lequel des doublons fait foi, jamais par réécriture.
2. **La visite n'a rien modifié.** À la fin de chaque étape, et avant la clôture :

   ```
   git status --porcelain --untracked-files=all       # attendu : ` M docs/journal.md` et rien d'autre
   ```

   Toute autre ligne est un interdit du § 3 franchi ; le fichier se restaure et le constat va au
   journal.
3. **La couverture est totale, et le reste se voit.** À la clôture, `git ls-files` est parcouru
   contre les préfixes du tableau du § 4, écrits pour l'occasion dans un fichier jetable :

   ```
   git ls-files | grep -vE "$(paste -sd'|' prefixes.txt)"
   ```

   Ce contrôle **ne peut pas échouer** : l'étape 10 est le reste (§ 4). Sa valeur est de
   **montrer** ce qui y tombe. Un reste attendu (fichiers d'outillage à la racine) se lit et se
   ferme ; un reste inattendu — un répertoire entier que personne n'a expliqué — est un écart de
   famille **d** et prend son issue.
4. **Chaque écart a son issue, et aucun report ne survit à la clôture.** Le journal étant
   append-only, un report ne s'efface pas : il **s'apparie**. Les deux formules du § 5 sont
   fixées pour être comptées, et la ligne d'appariement ne répète jamais la première :

   ```
   grep -c 'issue à ouvrir (étape'      docs/journal.md   # les reports posés
   grep -c 'issue ouverte pour l.étape' docs/journal.md   # attendu : le même compte
   ```

   **Tant que les deux comptes diffèrent, la visite n'est pas finie et la clôture ne commence
   pas** — c'est la seule condition d'arrêt de ce chantier en dehors des dix étapes. Ensuite :
   chaque ligne d'étape porte soit « aucun écart », soit au moins un `#<n°>` ; chaque numéro
   cité existe (`gh issue view <n°> --json state,title`).
5. **La spec 09 est restée vraie.** À la clôture, chaque chemin cité entre accents graves dans ce
   fichier existe (`test -e`), et chaque `§` cité existe dans le fichier visé. Les noms courts du
   tableau du § 4 se résolvent d'abord par la règle « Lecture des cellules » du § 4 — préfixe
   `docs/specs/` pour la colonne des specs, dernier répertoire nommé dans la cellule pour la
   colonne des fichiers — et c'est le chemin résolu qui est testé, jamais le nom nu. Ne sont pas
   testés : les identifiants qui ne sont pas des chemins (`BLOC_MAX`, `AxisEvaluator`,
   `to-implement`, `locksCount(root) > 1`, les commandes shell) et les fichiers jetables du
   contrôle 3. Un chemin devenu faux se corrige dans la PR de clôture (§ 5, cas dégradé) — c'est
   la seule modification de ce fichier que la visite autorise.

## 10. Sorties du chantier

| Fichier | Rôle |
|---|---|
| `docs/specs/09-visite-guidee.md` | **créé** — ce fichier, committé avant la première étape |
| `docs/journal.md` | dix lignes d'étape, une ligne de clôture, plus les lignes automatiques du hook `journal.js` produites entre-temps (§ 6) |
| `ROADMAP.md` | une ligne ajoutée (append-only), chantier **20** (§ 7.2) |
| `AGENTS.md` | la ligne « Où en est le projet » mise à jour — une ligne, pas un historique (§ 7.1, geste 4) |
| issues GitHub | une par écart relevé, hors dépôt, chacune pointée depuis le journal (§ 5) |

Ces quatre fichiers sont ceux déclarés dans la ligne de `ROADMAP.md` du § 7.2 : la table des
sorties et la cellule « Sorties » disent la même chose, nom pour nom.

Définition de fini, reprise de l'issue #61 et rendue vérifiable par le § 9 : les dix étapes
parcourues et journalisées (contrôle 1), chaque écart relevé porteur de son issue (contrôle 4),
ce fichier toujours vrai en fin de visite (contrôle 5), et le dépôt inchangé par la visite
elle-même (contrôle 2).
