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
  ce chantier ne commit que `docs/journal.md`, `ROADMAP.md` et ce fichier ;
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
  l'étape, avec son pointeur et la mention « issue à ouvrir » ; l'issue est ouverte à la reprise
  et son numéro complète le journal par une **nouvelle** ligne, le fichier étant append-only ;
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

Colonnes du fichier, remplies ainsi :

```
| <horodatage>Z | chantier 20 — étape <n> | session Claude Code (visite) | étape <n>
« <titre> » parcourue ; <k> écart(s) : #<n°>, #<n°> | <chemins couverts> · `<sha court de main>`
| <ce qui reste, ou —> |
```

Trois exigences, chacune pour une raison :

- **le SHA court de `main`** ancre l'étape à un état du dépôt : sans lui, « le fichier disait
  ceci » n'est pas rejouable ;
- **les numéros d'issue** figurent dans la ligne, ou la mention « aucun écart » ; c'est ce qui
  rend le contrôle 4 du § 9 mécanique ;
- **append-only** : une ligne d'étape ne se réécrit jamais, un complément s'ajoute en fin de
  fichier.

Les lignes s'écrivent dans `docs/journal.md` du checkout principal, sur `main`, sans commit ;
elles restent non committées jusqu'à la clôture (§ 7). Le hook `journal.js` n'ajoute rien dans
cet état, sa branche `Stop` sortant sans écrire quand la branche est `main`.

## 7. Clôture du chantier

La clôture est le **seul** moment où ce chantier écrit dans git, et elle produit **une seule PR
`docs:` pour toute la visite** : pas de PR intermédiaire, quelle que soit la durée du parcours.
Motif : une PR par tranche d'étapes multiplierait les cycles de revue sur un contenu qui ne
décide rien, alors que les lignes de journal sont append-only et ne gênent personne tant
qu'elles restent dans le checkout principal (§ 6).

1. si un front `/roadmap` est ouvert, déclarer « **pause roadmap** » et attendre sa fin : dès
   qu'un **second** verrou `/feature` existe, `guard-git.js` refuse `commit` et `push` dans le
   checkout principal (garde `locksCount(root) > 1`, § 2) ;
2. `/feature 61` : le verrou `61` est posé (`node .claude/hooks/feature-lock.js lock 61`), ce
   qui autorise une branche portant ce numéro — **`docs/visite-61`** ;
3. commit unique `docs:` portant les onze lignes de journal, la ligne d'état de `ROADMAP.md`, et
   les corrections de chemin éventuelles du § 4 ; **aucun agent `dev` n'est lancé** : il n'y a
   pas de code à écrire, et le travail est déjà dans le checkout principal ;
4. PR `docs:` avec le label `to-review`, revue Codex à l'ouverture, une passe de correction,
   `gh pr merge --auto --squash --delete-branch`, puis `unlock 61` — le § 3 du skill `feature`
   s'applique tel quel.

Deux PR sur la même issue #61 (celle qui porte cette spec, celle de clôture) : c'est la forme
déjà suivie au chantier 19 sur l'issue #56 — PR #58 pour la spec, PR #59 pour les panneaux.

Ligne à ajouter à `ROADMAP.md` (append-only, colonnes de la table) :

```
| 20 | Visite guidée du projet — dix étapes commentées, une issue par écart, aucun code | 09 (2026-08-30) | 19 | `docs/specs/09-visite-guidee.md` | #61 | spec écrite |
```

**Les sorties déclarées se limitent à ce fichier**, et c'est délibéré : `docs/journal.md` et
`ROADMAP.md` sont append-only et écrits par tous les chantiers ; les déclarer en sortie ferait
écarter, par la condition 5 du § 11.3 de la spec 08 (chevauchement de sorties), tout front
concurrent, alors que deux ajouts en fin de fichier ne se disputent rien. La dépendance est
**19** : les quatre panneaux mergés au chantier 19 sont la matière de plusieurs étapes.

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

1. **Les dix étapes sont journalisées.**

   ```
   grep -c 'chantier 20 — étape' docs/journal.md      # attendu : 10
   ```

   Une étape manquante, ou une même étape journalisée deux fois, se lit dans le compte.
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
4. **Chaque écart a son issue.** Chaque ligne de journal d'étape porte soit « aucun écart », soit
   au moins un `#<n°>` ; chaque numéro cité existe et est ouvert
   (`gh issue view <n°> --json state`). Une ligne qui décrit un écart sans numéro et sans la
   mention « issue à ouvrir » du § 5 est le défaut que ce contrôle attrape.
5. **La spec 09 est restée vraie.** À la clôture, chaque chemin cité entre accents graves dans ce
   fichier existe (`test -e`), et chaque `§` cité existe dans le fichier visé. Un chemin devenu
   faux se corrige dans la PR de clôture (§ 5, cas dégradé) — c'est la seule modification de ce
   fichier que la visite autorise.

## 10. Sorties du chantier

| Fichier | Rôle |
|---|---|
| `docs/specs/09-visite-guidee.md` | **créé** — ce fichier, committé avant la première étape |
| `docs/journal.md` | onze lignes ajoutées : une par étape, une de clôture (§ 6) |
| `ROADMAP.md` | une ligne ajoutée (append-only), chantier **20** (§ 7) |
| issues GitHub | une par écart relevé, hors dépôt, chacune pointée depuis le journal (§ 5) |

Définition de fini, reprise de l'issue #61 et rendue vérifiable par le § 9 : les dix étapes
parcourues et journalisées (contrôle 1), chaque écart relevé porteur de son issue (contrôle 4),
ce fichier toujours vrai en fin de visite (contrôle 5), et le dépôt inchangé par la visite
elle-même (contrôle 2).
