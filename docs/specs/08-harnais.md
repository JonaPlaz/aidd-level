# 08 — Harnais de développement

Objectif (Jonathan, 2026-08-26) : **le dépôt rendu atteint lui-même le niveau Gold de la
grille qu'il applique** — context engineering, behavior, boucles ; intervention « jamais, une
fois la tâche cadrée » ; trois chantiers de front. Et le critère « comment tu l'as
construit ? » note ce harnais **s'il est montré** : versionné, décrit dans `docs/methode.md`,
constaté dans `docs/harness.md`, visible dans la vidéo.

Décision de fond : **harnais maison, framework AIDD écarté** (examiné en v5.9.0 ; motif : le
critère est écrit à la deuxième personne, et 47 skills à apprendre sur la fenêtre est le seul
pari capable de coûter le rendu). Deux emprunts de forme, cités comme art antérieur :
déclenchement par label, et « iron rule » anti-retour.

## 1. La boucle

```
issue `to-implement`
  └─► skill /feature <n°>
        ├─► agent spec : la spec existe-t-elle dans docs/specs/ ? sinon l'écrit → ARRÊT humain
        ├─► agent dev (worktree) : branche, code, tests, `make test lint dup`, push, PR, label `to-review`
        ├─► CI : tests + analyse statique + duplication
        ├─► Codex : revue automatique à l'ouverture (réglage cloud, seul geste manuel)
        ├─► skill : rebase, revue Codex, passes de correction jusqu'à validation
        └─► `gh pr merge --auto --squash --delete-branch` — merge quand les verrous sont verts
```

Deux harnais, jamais en contact direct : **Claude Code implémente, Codex review**, la PR est
le seul rendez-vous. Pas d'agent reviewer maison (doublon de Codex).

**Arbitrage proposé — la correction tourne en local, pas en CI.** Le brief le laisse « à
trancher au montage ». Motifs : aucun secret d'API dans le dépôt (case obligatoire du rendu),
coût dans l'abonnement, le piège `allowed_bots` (issue #1299) disparaît avec le bot, et le flux
est identique vu du dépôt. Conséquence : `claude-code-action` n'est pas installée.
**À valider par Jonathan.**

## 2. Agents — deux, chacun avec son allocation justifiée

Champs vérifiés le 2026-08-28 (doc *sub-agents*) : `model`, `effort`, `maxTurns`,
`permissionMode`, `isolation`, `tools`, `skills`, `hooks`. Principe : **l'effort va où se
prennent les décisions, pas où se produit le volume.**

| | `spec` | `dev` |
|---|---|---|
| Fichier | `.claude/agents/spec.md` | `.claude/agents/dev.md` |
| `model` | `opus` | `sonnet` |
| Pourquoi | une spec mal raisonnée se paie sur toute la chaîne | travail cadré par une spec validée, exécutable par un modèle plus petit |
| `effort` | `high` | `medium` |
| Pourquoi | raisonnement contrastif, seuils, cas dégradés | implémentation guidée ; `high` si un test de calibration casse (relance manuelle) |
| `maxTurns` | 40 | 80 |
| Pourquoi | borne 1 ; une spec tient en une session courte | borne 1 ; code + tests + PR + une passe de correction |
| `permissionMode` | `acceptEdits` | `acceptEdits` |
| Pourquoi | écrit dans `docs/specs/` sans demander ; ne lance rien d'externe | édite et lance `make`, `git`, `gh` ; les hooks gardent les couches |
| `isolation` | — | `worktree` |
| `tools` | Read, Grep, Glob, Write, Edit, WebFetch, WebSearch | Read, Grep, Glob, Write, Edit, Bash |
| `skills` | — | `feature` |

⚠️ **Non vérifié** : le comportement quand `effort` n'existe pas pour le modèle demandé. À
constater sur la PR jetable. Les valeurs de `maxTurns` ne sont sourcées nulle part :
adaptation assumée, revue au journal si une borne est atteinte.

## 3. Skills — deux

| Skill | Frontmatter | Rôle |
|---|---|---|
| `bootstrap` | `disable-model-invocation: true`, `allowed-tools: Bash Write Read` | § 07, une fois |
| `feature` | `argument-hint: [issue-number]` (invocable par le modèle depuis le 2026-08-30, chantier 16) | enchaîne spec → validation → dev → PR → attente de review → correction → merge auto, sans intervention après validation. Mode `--trivial` pour la PR jetable |

**Attente de review (local)** : l'agent `dev` rend la main dès la PR ouverte ; **le skill
possède la boucle** (une seule responsabilité, remarque Codex sur la PR #13). Les opérations
git sur la branche sont exécutées par **le propriétaire du checkout** (agent `dev` relancé,
ou skill pour une PR née du checkout courant) ; jamais de `git -C`.

**Une revue Codex par PR, à l'ouverture** — arbitré par Jonathan le 2026-08-29, après
épuisement du quota de revues en une soirée (3 à 5 re-revues par PR). Le skill attend le
verdict d'ouverture (revue ou réaction 👍 ; `@codex review` ne sert qu'aux re-revues
exceptionnelles) avec `gh api --paginate` sur `…/reviews`, `…/comments` et
`…/issues/{n}/reactions`, plafond `REVIEW_WAIT_MAX = 20 min` (valeur initiale d'après la PR
#13 : revue reçue ≈ 12 min après le dernier push ; délais suivants consignés dans
`docs/harness.md`). Puis **une passe de correction** qui traite toutes les remarques, **rebase et push
d'abord** (amendé le 2026-08-30, remarque Codex sur la PR #38 : un rebase après coup réécrit
le SHA cité), puis **une réponse tracée par remarque** dans le fil de la PR (appliqué en
`<sha>` présent sur la branche, ou non appliqué et motif), et seulement alors
`gh pr merge --auto`. **Rien ne s'arme avant le verdict
d'ouverture** — c'est le point que Jonathan a fixé : GitHub ne merge jamais avant que Codex
ait eu le temps de revoir. Re-revue jamais automatique : seulement si la correction change
une décision de scoring, ou sur demande. Délai dépassé ou quota épuisé → journal, label
`blocked`, arrêt.

**Armement par la plateforme, pas par l'agent** (amendé le 2026-08-30, chantier 15). Constat
du jour : deux PR ouvertes à la main, hors skill, n'ont armé personne — le 👍 de Codex est
resté sans suite jusqu'à ce qu'un humain s'en aperçoive. Le verdict Codex est un événement
GitHub, distant, invisible d'un hook Claude Code ; et une réaction ne déclenche **aucun**
événement Actions (seuls revues et commentaires en déclenchent). Donc un workflow
`.github/workflows/auto-merge-after-codex.yml`, en **cron** (toutes les 10 min) et à la
demande, qui, pour chaque PR ouverte, non brouillon, sans label `blocked`, dont l'auteur de la
réaction `+1` est `chatgpt-codex-connector[bot]` **et** dont cette réaction est postérieure au
dernier commit de la branche, arme `gh pr merge --auto --squash --delete-branch`. GitHub merge
ensuite seul quand `ci` est vert et la branche à jour (`strict`). Le chemin « avec remarques »
reste au skill : Actions ne sait pas juger qu'une remarque est traitée ; le skill corrige,
répond, puis arme comme avant. « Rien ne s'arme avant le verdict d'ouverture » tient : le
cron ne lit que le 👍.

Historique : « une passe » (26 août) → « autant de passes que Codex en demande » (29 août,
PR #15) → « une revue, une passe, réponses tracées » (29 août, quota). Chaque étape est au
journal.

**Le cycle est imposé, pas rappelé** (amendé le 2026-08-30, chantier 16). Constat : quatre PR
ouvertes à la main en une matinée, hors skill, avec à chaque fois un pas du cycle oublié
(label, armement, merge synchrone tenté). `disable-model-invocation: true` sur `feature`
(posé au chantier 0 sans motif écrit) obligeait un humain à taper `/feature` — l'inverse du
but. Levé : la session lance le skill elle-même. Et le hook `guard-git` refuse `gh pr create`
hors d'un run du skill et tout `gh pr merge` sans `--auto` ni `--disable-auto`. Le run se
prouve par un **verrou qui lui appartient** (remarque Codex sur la PR #41 : un verrou unique
partagé cassait les runs concurrents en worktrees) : `<git common dir>/feature-locks/<n°>`,
posé par `node .claude/hooks/feature-lock.js lock <n°>` au premier geste du skill, retiré
par `unlock <n°>` au dernier, visible depuis tous les worktrees, jamais committé ; il
n'autorise que les branches portant ce numéro (`feat/<n°>-…`, `docs/spec-<n°>` ;
`trivial-<horodatage>` ↔ `trivial/…`). Les deux commandes sont dans la liste `allow` de
`.claude/settings.json` : le run ne s'arrête pas pour une permission. Les flags globaux de
`gh pr` (`-R/--repo`) sont parsés avant le sous-commande, jamais supposés absents. Une PR de
docs passe aussi par une issue et `/feature`.

**Iron rule** : une fois l'agent dev engagé sur une issue, le skill ne revient pas au routage ;
il termine, ou s'arrête sur une borne.

## 4. Hooks — quatre, tous testés par violation

Versionnés dans `.claude/settings.json` (sinon ils ne comptent pas), scripts dans
`.claude/hooks/*.js` (Node, présent sur la machine — **version non vérifiée**). Format vérifié :
`PreToolUse` bloque par exit 2 ; entrée JSON sur stdin (`tool_name`, `tool_input`, `cwd`).

| Hook | Événement · matcher | Règle | Test de déclenchement (doute 7) |
|---|---|---|---|
| `guard-layers.js` | `PreToolUse` · `Edit\|Write` | fichier sous `src/Domain/` dont le contenu contient `use AiddLevel\Application` ou `use AiddLevel\Infrastructure` → exit 2 | écrire volontairement un `use` interdit, constater le refus |
| `guard-git.js` | `PreToolUse` · `Bash` (`git commit`, `git push`, `gh pr create`, `gh pr merge`) | commit : fichiers (index, ou `-a`) touchant `src/Domain/` **et** `src/Infrastructure/` → exit 2 ; chemin déclaré dans `.worktreeinclude` → exit 2. Push : `--force` ou `-f` nu → exit 2 (`--force-with-lease` seul admis, pour le rebase). `gh pr create` : branche courante sans verrou `feature-locks/<n°>` correspondant → exit 2. `gh pr merge` sans `--auto` ni `--disable-auto` → exit 2 (chantier 16) | `.claude/hooks/tests/guard-git.test.js`, exécuté par la CI : deux couches stagées ; `git push --force` ; `gh pr create` sans verrou, avec le verrou d'un autre run, avec `-R` avant le sous-commande ; `gh pr merge --squash` ; et les voisins admis |
| `format.js` | `PostToolUse` · `Edit\|Write` | fichier `*.php` → `make fmt FILE=…` (php-cs-fixer dans Docker, **version non vérifiée** pour PHP 8.5) | éditer un fichier mal indenté, constater la réécriture |
| `journal.js` | `PostToolUseFailure` · `Bash` ; `SubagentStop` ; `Stop` (seulement hors `main` avec travail non committé) | ajoute une ligne à `docs/journal.md` (format § 6) avec `git rev-parse HEAD`, branche, et pour `PostToolUseFailure` la commande échouée — **le journal est alimenté par hook, pas par la bonne volonté du modèle** | faire échouer `make test`, constater la ligne |

Un hook qui ne se déclenche pas au test est **retiré**, pas laissé mort.

`permissions` dans le même `settings.json` : `allow` sur `make`, `git` (status, diff, log,
add, commit, push, fetch, rebase, checkout, switch), `gh` (pr, issue, api, run) et `sleep`
(boucle d'attente) ; `deny` sur `rm -rf`, `Edit`/`Write` de `.brief/`. `Read(./.brief/**)`
est **exclu** du deny (le brief doit rester lisible). Le `deny` `git push --force:*` posé au
premier montage est **retiré** : il bloquait aussi `--force-with-lease`, requis par le rebase ;
c'est le hook `guard-git` qui refuse `--force`, `-f` et les refspecs `+…`.
`worktree.baseRef` reste `fresh` (branche depuis `origin/main`).

## 5. CI — `.github/workflows/ci.yml`

Déclenchée sur `pull_request` et `push` sur `main`. Un job `ci` :
`shivammathur/setup-php@v2` (PHP 8.5 — **disponibilité du tag non vérifiée**, repli : job
dans le conteneur `php:8.5-cli-alpine`), `composer install --no-progress`, `make test`,
`make lint` (PHPStan, niveau max), `make dup` (duplication, seuil en constante
`DUPLICATION_MAX_PCT` dans le Makefile, **valeur non sourcée** — proposition 3 %, à égalité
avec le meilleur profil fourni ; à valider). Résultat visible publiquement. Après le premier
run vert, second `PUT` de protection avec `required_status_checks.contexts = ["ci"]` et
`strict: true`.

## 6. Journal — `docs/journal.md`

Auditabilité, pas observabilité. Git, PR, commentaires Codex, CI journalisent déjà ; le
fichier tient **ce qui n'a pas produit de commit**. Append-only, une ligne par événement :

```
| horodatage ISO | étape ou chantier | acteur (agent · modèle · effort) | événement | pointeur (SHA, PR, run, chemin) | suite |
```

Une ligne sans pointeur ne vaut rien (même réserve que le projet applique au déclaratif).
Conventions GenAI OpenTelemetry : connues, écartées (disproportionnées), citées dans la méthode.

## 7. Bornes — où la boucle s'arrête

1. `maxTurns` par agent (§ 2) ;
2. **une** revue Codex par PR et **une** passe de correction, réponses tracées (2026-08-29,
   après un passage par « autant de passes qu'il faut » qui a épuisé le quota) ;
3. arrêt net : tuer les sessions, les worktrees gardent leur travail ; `enforce_admins: false`
   laisse Jonathan merger à la main.

**Un seul point d'arrêt humain : la validation d'une spec.** Point de non-retour : une heure
d'écart entre montage prévu et réel → boucle en manuel (Claude implémente, Jonathan lance la
review Codex, Claude corrige) ; `docs/harness.md` le dit.

## 8. Parallélisme et intégration

Le degré se **calcule** depuis le graphe de dépendances de `ROADMAP.md` : deux chantiers sont
simultanés si leurs specs ne partagent aucun fichier de sortie et qu'aucun ne dépend de
l'autre. Attendu : noyau de domaine seul, puis quatre axes de front, puis sortie/robustesse.

- Rebase sur `main`, jamais de merge de `main` dans la branche ; `required_linear_history` ;
  push par `--force-with-lease` uniquement (le check requis est `strict`, une branche en
  retard ne merge pas — message GitHub constaté sur #15).
- Branches courtes : une issue, une PR, squash au merge.
- `ROADMAP.md` et `docs/journal.md` append-only ; `composer.json` par un seul chantier à la
  fois (dépendance déclarée dans la roadmap).
- Une tentative de rebase automatique par l'agent qui possède la branche, puis `blocked`.
- ⚠️ **Non éprouvé** : trois worktrees simultanés et le rebase concurrent (doute 6). Trois
  chantiers triviaux avant les vrais.

## 9. PR jetable — lever les doutes 1 à 4 avant le premier vrai chantier

`/feature --trivial` : une ligne ajoutée au README, branche, PR, label. Observer :
(1) Codex publie-t-il ? (2) le skill voit-il la revue et corrige-t-il ? (3) forme de la sortie
Codex — check ou commentaires ; (4) sans objet en local (pas de bot). Chaque constat va au
journal avec l'URL de la PR ; `REVIEW_WAIT_MAX` est fixé d'après le délai mesuré.

## 10. Ce que la vidéo montre

Une issue étiquetée, le skill lancé, la PR ouverte avec la revue Codex, le merge automatique,
puis `docker run … evaluate profiles/self` sur le dépôt lui-même. Deux minutes, muette,
lisible sans le son (texte à l'écran, pas de voix).

## 11. Lancement autonome de la roadmap (chantier 17, 2026-08-30)

Demande de Jonathan (issue #46) : « j'arrive dans la session, je demande un truc — ou
directement, tu lances tout ce qu'il y a à faire, si possible en parallèle, tout ce qui est
sur la roadmap ». Constat de départ : la session lance `/feature <n°>` **un par un**, à la
main, alors que le parallélisme est calculé depuis `ROADMAP.md` depuis le chantier 0 (§ 8) et
que six fronts ont déjà tenu ensemble le 2026-08-29 (`docs/harness.md`). Ce qui manque n'est
pas le parallélisme, c'est **le geste qui l'ouvre**.

Les faits de plateforme cités ici viennent de la documentation Claude Code consultée le
**2026-08-30** : pages *hooks*, *skills*, *sub-agents*, *scheduled tasks*, *goal*. Ce qui n'a
pas pu y être vérifié est marqué **non vérifié**.

### 11.1 (a) Le déclencheur : routine `SessionStart` ou skill invoqué ?

| | Hook `SessionStart` | Skill `roadmap` invocable par le modèle |
|---|---|---|
| Ce qu'il peut faire | **injecter du contexte, rien d'autre** : sa sortie standard (ou le champ `additionalContext`) devient du texte que le modèle voit et peut suivre | tout ce que la session peut faire : lancer des sous-agents, appeler `gh`, écrire au journal |
| Ce qu'il ne peut pas | **démarrer un tour** ; aucun outil ne s'exécute tant que Jonathan n'a pas envoyé un message (demande ouverte `anthropics/claude-code#10808`, « Autonomous Messages After SessionStart ») | s'invoquer sans qu'un tour existe — il lui faut un premier message, **ou** un tir de tâche planifiée (§ 11.5) |
| Coût | s'exécute à chaque démarrage ; défaut documenté de délai : 600 s — un scan lent rend le démarrage collant | nul tant qu'il n'est pas invoqué : seule la `description` est en contexte, le corps se charge à l'invocation |
| Matchers | `startup`, `resume`, `clear`, `compact`, `fork` | — |

**Tranché : le skill décide, le hook ne fait que constater.**

- `.claude/skills/roadmap/SKILL.md`, **sans** `disable-model-invocation` : sa description reste
  en contexte, la session l'invoque elle-même dès le premier message de Jonathan, quel qu'en
  soit le sujet. C'est la même levée qu'au chantier 16 pour `feature`, et le même motif : un
  cycle qu'il faut taper à la main n'est pas un harnais.
- Un cinquième hook, `SessionStart` (matcher `startup|resume|clear`), qui lance
  `node .claude/hooks/roadmap-ready.js` et injecte **le tableau des fronts prêts** — des faits,
  jamais un ordre. Il ne lance rien. Délai `ROADMAP_SCAN_TIMEOUT = 15 s` (adaptation assumée :
  le défaut documenté de 600 s est inacceptable au démarrage) ; dépassement, `gh` non
  authentifié, dépôt absent → **sortie vide, exit 0**, le démarrage n'est jamais cassé.
- Le hook et le skill appellent **le même script**, seule implémentation de la règle de
  maturité (§ 11.3) : le hook pour afficher, le skill pour décider. Pas de règle en double
  (règle 7 de la revue).

Écarté, et pourquoi : `/loop` et `/goal` répondent au même besoin (« travailler une file
d'issues étiquetées jusqu'à ce qu'elle soit vide » est un exemple de la page *goal*), mais ce
sont des **commandes intégrées** : une commande intégrée invoquée par le modèle « arrive à
Claude comme du texte, elle ne s'exécute pas » (page *scheduled tasks*). Elles restent la
reprise en main de Jonathan (§ 11.7), pas le mécanisme.

⚠️ **Constaté le 2026-08-30** : un skill dont le **frontmatter** change n'est pris en compte
qu'au **tour suivant** (levée de `disable-model-invocation` sur `feature`, chantier 16). La doc
dit seulement que les modifications de `SKILL.md` sont détectées « dans la session courante,
sans redémarrage » ; la granularité au tour est **non vérifiée**. Conséquence pratique :
`/roadmap` n'est invocable qu'au tour qui suit sa création — et si `.claude/skills/` n'existait
pas au démarrage, il faut redémarrer (doc *skills*, « live change detection »).

### 11.2 Vue d'ensemble de la boucle

```
premier message de Jonathan (n'importe lequel)
  └─► skill /roadmap  (session principale — décide, ne code pas, ne touche pas à git)
        ├─► node .claude/hooks/roadmap-ready.js  → fronts prêts, créneaux libres
        ├─► verrou `roadmap-<horodatage>`
        ├─► pour chaque front retenu (≤ MAX_CONCURRENT_FRONTS) : un sous-agent `front`
        │     └─► skill `feature` préchargé : verrou <n°> → agent `dev` (worktree) → PR
        │           → attente revue Codex → passe de correction → réponses tracées
        │           → rebase → `gh pr merge --auto` → déverrouillage → journal
        ├─► tâche planifiée de remplissage (ROADMAP_REFILL_INTERVAL) qui ré-invoque /roadmap
        └─► compte rendu : un tableau, une ligne par front
```

Trois profondeurs : session → `front` → `dev`. La limite documentée est de **trois couches
sous la conversation principale** ; l'outil `Agent` n'est retiré qu'à la limite de profondeur,
**y compris pour un sous-agent d'arrière-plan** (doc *sub-agents*, « premier filtre »). Donc
`front` peut lancer `dev`. La limite de concurrence par défaut est de **20 sous-agents**
(`CLAUDE_CODE_MAX_CONCURRENT_SUBAGENTS`) : à trois fronts, huit sous-agents au plus, elle n'est
jamais atteinte.

### 11.3 (b) Ce qui rend un front « prêt »

Calculé, jamais jugé. `roadmap-ready.js` ne rend un front prêt que si **les six** conditions
tiennent :

1. **Une issue ouverte étiquetée `to-implement`** (`gh issue list --label to-implement --state open`)
   et **sans** label `blocked`.
2. **Ses dépendances sont mergées** : la colonne « dépend de » de sa ligne dans `ROADMAP.md` ;
   chaque numéro cité doit avoir un état contenant `mergé`. `ROADMAP.md` étant **append-only**,
   l'état d'un chantier est celui de **sa dernière ligne** ; ses « Sorties » sont celles de sa
   dernière ligne qui en déclare (les lignes d'état écrivent `—`).
3. **Aucun verrou en cours sur ce numéro** : le fichier `<git common dir>/feature-locks/<n°>`
   est absent (lu via `locksDir()` de `.claude/hooks/lib.js`, pas réimplémenté).
4. **Aucune PR ouverte pour lui** : ni branche portant le numéro comme jeton
   (`(^|[-/])<n°>([-/]|$)`, même règle que `guard-git`), ni corps contenant `Closes #<n°>`.
5. **Aucun chevauchement de sorties** avec un front déjà ouvert ou retenu dans la même passe
   (§ 8 : « deux chantiers sont simultanés si leurs specs ne partagent aucun fichier de
   sortie ») ; `composer.json` compte pour un chantier à la fois (AGENTS.md).
6. **Sa spec est présente dans `docs/specs/` et committée sur `origin/main`.** Un front dont la
   spec manque n'est **jamais ouvert** : il ne consomme pas de créneau, il est listé « à
   spécifier » dans le compte rendu. Motif : une spec nouvelle est le seul arrêt humain (§ 7) ;
   en ouvrir trois d'un coup transformerait un arrêt en trois.

**Frein global** : si **une PR ouverte porte le label `blocked`**, `roadmap` n'ouvre **aucun**
front et le dit. C'est le même signal pour toutes les causes d'arrêt (quota Codex, conflit de
rebase, plafond d'attente) — un état lisible de `gh`, sans mémoire à tenir entre deux
invocations.

Cas dégradés, tous silencieux et sans ouverture : ligne de roadmap illisible ou absente pour
une issue ; « dépend de » citant un chantier inconnu ; deux lignes contradictoires (la dernière
gagne, l'écart est signalé dans la sortie) ; `gh` en échec ou hors ligne ; dépôt git absent. Le
script **ne bloque jamais** et n'écrit rien : il imprime, exit 0.

### 11.4 (c) Plafond de fronts simultanés

`MAX_CONCURRENT_FRONTS = 3`, constante nommée dans `roadmap-ready.js` avec sa justification en
commentaire, citée par le skill.

Origine : § 8 et `ROADMAP.md` (« la spec du harnais prévoit de commencer à trois », doute 6 du
brief). Ce n'est pas une limite de plateforme (20 sous-agents concurrents par défaut) mais une
limite d'intégration : le check `ci` est requis en mode `strict`, chaque merge remet les autres
branches en retard (cascade constatée le 2026-08-29, ~3 min par PR), et le quota de revues
Codex s'épuise (~25 revues en une soirée, `docs/harness.md`). Six fronts ont tenu une fois ;
trois est le pas suivant assumé, **à réviser au journal** dès qu'une passe complète tourne sans
`blocked`.

Ordre de service quand plus de fronts sont prêts que de créneaux : **numéro de chantier
croissant** — déterministe, et le plus petit numéro est le plus ancien de la chaîne de
dépendances.

### 11.5 (d) Ce que fait la session pendant l'attente

**Chaque front possède sa boucle.** Un sous-agent `front` (`.claude/agents/front.md`) tient,
pour son issue, exactement le cycle du § 3 : verrou, agent `dev`, PR, attente de la revue
d'ouverture (`REVIEW_WAIT_MAX = 20 min`, inchangé), une passe de correction, réponses tracées,
rebase, `gh pr merge --auto --squash --delete-branch`, déverrouillage, journal. Le skill
`feature` lui est **préchargé** (`skills: feature`) : son contenu entre dans le contexte du
sous-agent au démarrage (doc *sub-agents*), ce qui n'est possible que depuis la levée de
`disable-model-invocation` au chantier 16.

| | `front` |
|---|---|
| `model` / `effort` | `sonnet` / `high` |
| Pourquoi | pas de production de code (c'est `dev`), mais des décisions : cette remarque touche-t-elle un seuil ? faut-il `blocked` ? — l'effort va où se prennent les décisions (§ 2) |
| `maxTurns` | 120 — arithmétique : jusqu'à 20 sondages de revue + 20 sondages de merge à 60 s, plus le routage, la correction et les réponses. **Adaptation assumée**, revue au journal si atteinte |
| `permissionMode` | `acceptEdits` |
| `isolation` | — (aucune : il ne modifie aucun fichier) |
| `tools` | Read, Grep, Glob, Bash, **Agent** (sans quoi il ne peut pas lancer `dev` ; nom d'outil `Agent` d'après la doc *sub-agents* — **à confirmer à l'épreuve**) |
| `background` | par défaut (arrière-plan) : les fronts tournent pendant que la session répond |

**Le `front` ne touche jamais au checkout principal.** Les opérations git sur une branche
restent au propriétaire du checkout (§ 3) : c'est l'agent `dev`, relancé dans son worktree.
Motif : l'incident du chantier 11 (`docs/harness.md`, « un commit a atterri sur `main` local
pendant qu'un fond changeait de branche ») deviendrait la norme à trois fronts. Deux garde-fous :

- `roadmap` pose un verrou `roadmap-<horodatage>` en plus des verrous par numéro (il sert aussi
  d'exclusion mutuelle : une deuxième invocation qui le voit ne ré-ouvre rien, elle remplit
  seulement les créneaux libres) ;
- **extension de `guard-git`** : dès que **plus d'un verrou** est présent, un `git checkout`,
  `switch`, `rebase`, `merge`, `commit` ou `push` dont le `cwd` est le **checkout principal**
  (`git rev-parse --git-dir` == `--git-common-dir`) est refusé (exit 2). Et un verrou
  `roadmap-*` **n'autorise aucune branche** à `gh pr create`, comme `trivial-*` n'autorise que
  `trivial/…`.

**La session, elle**, ne fait rien de git ni de `gh` pendant l'attente. Elle : annonce en une
ligne les fronts ouverts ; répond à Jonathan s'il demande autre chose ; à chaque retour de
front (le résultat d'un sous-agent d'arrière-plan « parvient à Claude comme une notification
d'achèvement dans un tour ultérieur », doc *sub-agents*), écrit une ligne au journal et
**rappelle `/roadmap`** pour remplir le créneau libéré.

**Remplissage sans Jonathan.** Qu'une session inactive soit réveillée par la fin d'un
sous-agent d'arrière-plan est **non vérifié** hors `/goal`. Le skill ne s'appuie donc pas
dessus : à son premier passage il planifie **une** tâche récurrente (`CronCreate`,
`ROADMAP_REFILL_INTERVAL = 10 min`, aligné sur le cron `auto-merge-after-codex` du chantier 15)
dont l'invite est `/roadmap`. Les tâches planifiées « ne se déclenchent que pendant que Claude
Code tourne et est inactif », entre deux tours, et un tir manqué n'est pas rattrapé (doc
*scheduled tasks*) — c'est un rattrapage, pas une horloge. Le décalage volontaire (« jitter »)
peut retarder un tir infra-horaire jusqu'à la moitié de l'intervalle : sans conséquence.

**La boucle porte sa borne** (spec 02 : une boucle est une relance **et** une borne) :
`roadmap` **annule sa propre tâche** (`CronDelete`) dès qu'il ne reste ni front prêt ni front
en cours, ou dès que le frein global s'applique (une PR ouverte `blocked`). À défaut, la
plateforme l'expire seule au bout de 7 jours.

### 11.6 (e) Bornes et arrêts

1. `MAX_CONCURRENT_FRONTS` — au-delà, on attend un merge.
2. **Spec absente = pas de front** (§ 11.3, condition 6). Au plus **un** agent `spec` lancé à
   la fois, et il se termine sur « spec écrite, à valider » : arrêt humain (§ 7), inchangé.
3. **`blocked` n'est jamais levé par un agent** : une issue ou une PR étiquetée `blocked` gèle
   l'ouverture de tout nouveau front jusqu'à un geste de Jonathan.
4. **Quota Codex** : un front qui lit un message de quota épuisé pose `blocked`, journalise et
   s'arrête (§ 3, inchangé) ; le frein global (§ 11.3) empêche alors les autres fronts de
   partir brûler ce qu'il reste. Un front déjà lancé va au bout de sa passe.
5. `REVIEW_WAIT_MAX = 20 min` par front, inchangé.
6. **Rebase en cascade** — amendement au § 8, **à valider** : « une tentative de rebase, puis
   `blocked` » vaut pour un rebase **en conflit**. Un rebase **mécanique** (sans conflit) parce
   qu'un autre front a mergé entre-temps est rejoué jusqu'à
   `REBASE_ATTEMPTS_MAX = MAX_CONCURRENT_FRONTS − 1` fois : avec N fronts, une branche ne peut
   être doublée que par les N − 1 autres. Sans cet amendement, ouvrir trois fronts produit
   mécaniquement deux `blocked` à l'intégration.
7. **Aucun front ne peut poser de question.** L'outil `AskUserQuestion` est retiré de tout
   sous-agent (doc *sub-agents*, premier filtre) : toute question devient `blocked` + journal +
   compte rendu. Corollaire de permissions : la liste `allow` de `.claude/settings.json` doit
   couvrir tout ce qu'un front exécute (`gh`, `git`, `make`, `sleep`, `feature-lock.js`, et à
   ajouter `node .claude/hooks/roadmap-ready.js`) — un sous-agent d'arrière-plan « fait
   remonter chaque demande de permission dans la session principale », et une demande non
   couverte bloquerait les trois fronts d'un coup.
8. `maxTurns` de `front` (120) et de `dev` (80), inchangés par ailleurs.

### 11.7 (f) Ce que Jonathan garde

- **La validation de toute spec nouvelle** — seul point d'arrêt humain (§ 7), et la raison pour
  laquelle un front sans spec ne s'ouvre pas.
- **Le retrait d'un `blocked`** : rien ne repart tant qu'il est là.
- **Deux mots**, reconnus par la session (instruction du skill et de `CLAUDE.md § Flow`, pas un
  hook — aucun hook ne peut arrêter un sous-agent) :
  - « **pause roadmap** » : plus aucun front nouveau, tâche de remplissage annulée ; les fronts
    en cours vont au bout (leur travail est déjà dans une PR) ;
  - « **stop roadmap** » : idem, plus `TaskStop` sur chaque front en cours et
    `gh pr merge <n> --disable-auto` sur les PR armées — sinon une PR se merge après l'arrêt.
    Les worktrees gardent leur travail (§ 7). Une ligne de journal par front arrêté.
- **La reprise en main documentée** : `/goal <condition>` (« … jusqu'à ce que la file d'issues
  `to-implement` soit vide ») ou `/loop 10m /roadmap`, tapés par lui, si un jour la tâche
  planifiée ne suffit pas. Le skill imprime la ligne prête à coller dans son compte rendu.

### 11.8 Sorties du chantier

| Fichier | Rôle |
|---|---|
| `.claude/skills/roadmap/SKILL.md` | le skill, invocable par le modèle, `argument-hint: [--dry-run]` |
| `.claude/hooks/roadmap-ready.js` | la règle de maturité, seule implémentation ; CLI imprimant les fronts prêts, retenus, à spécifier, et les freins |
| `.claude/hooks/tests/roadmap-ready.test.js` | tests unitaires, exécutés par la CI (comme `guard-git.test.js`) |
| `.claude/hooks/guard-git.js` | extension : checkout principal en lecture seule pour git dès qu'il y a plus d'un verrou ; verrou `roadmap-*` n'autorisant aucune branche |
| `.claude/agents/front.md` | l'agent qui tient un cycle `feature` complet, en arrière-plan |
| `.claude/settings.json` | hook `SessionStart`, `allow` de `roadmap-ready.js` |
| `CLAUDE.md` § Flow | trois lignes : `/roadmap` ouvre les fronts prêts, `feature` reste le cycle unitaire, « pause / stop roadmap » |
| `.claude/skills/feature/SKILL.md` | une phrase : lancé par un agent `front`, il ne travaille jamais dans le checkout principal |
| `ROADMAP.md` | une ligne ajoutée : `| 17 | Lancement autonome de la roadmap | 08 § 11 | 16 | .claude/skills/roadmap/, .claude/hooks/, .claude/agents/front.md, CLAUDE.md | #46 | à faire |` |

Ce chantier ne touche ni `src/` ni `tests/` : aucune décision de scoring, aucun seuil de
domaine. La règle 7 d'AGENTS.md (domaine + infrastructure) est sans objet.

### 11.9 Tests / épreuve

**Unitaire** (`roadmap-ready.test.js`, en CI — la règle de maturité est du code, elle se teste
comme `guard-git`). Chaque cas est un `ROADMAP.md` de fixture plus un état `gh` injecté par
fichier JSON, jamais par le réseau :

1. dépendance non mergée → front écarté ;
2. deux lignes pour le même chantier, la dernière dit `mergé` → dépendance satisfaite (règle
   « la dernière ligne gagne ») ;
3. sorties partagées avec un front déjà ouvert → écarté ;
4. verrou `feature-locks/<n°>` présent → écarté ;
5. PR ouverte portant le numéro, ou `Closes #<n°>` dans le corps → écarté ;
6. spec absente de `origin/main` → classé « à spécifier », jamais retenu ;
7. une PR ouverte `blocked` → **liste retenue vide**, frein global annoncé ;
8. cinq fronts prêts → **trois** retenus, dans l'ordre croissant ;
9. `gh` en échec, `ROADMAP.md` illisible, hors dépôt git → sortie vide, exit 0.

**Épreuve de bout en bout** — la preuve demandée par l'issue #46 : **deux issues prêtes, deux
fronts ouverts, deux PR mergées sans geste humain.**

1. Deux issues `to-implement`, specs déjà sur `origin/main`, sorties disjointes (au besoin, deux
   chantiers de documentation).
2. Jonathan ouvre la session et écrit **un message quelconque** (l'épreuve échoue s'il a dû
   taper `/roadmap`, `/feature`, `/goal` ou `/loop`).
3. Attendu, sans autre geste : deux sous-agents `front` en cours ; deux PR ouvertes avec label
   `to-review` ; deux verrous présents pendant le run, **plus aucun à la fin** ; deux PR
   `mergedAt` non nul, mergées en squash, branches supprimées ; chaque commit portant le
   trailer `Co-Authored-By: Claude <noreply@anthropic.com>` ; une ligne de journal par front
   avec pointeur (PR, SHA).
4. Preuve du plafond : quatre issues prêtes → trois fronts, le quatrième ouvert seulement après
   un merge, par un tir de la tâche de remplissage (visible au journal : l'horodatage du
   quatrième front est postérieur au `mergedAt` du premier).
5. Preuve du frein : poser `blocked` sur une PR ouverte, invoquer `/roadmap` → rien n'est
   ouvert, le motif est dit.
6. Preuve du garde-fou git : depuis le checkout principal, avec deux verrous présents, tenter
   `git checkout -b x` → refus du hook (test de déclenchement par violation, § 4).
7. Preuve du mot d'arrêt : « stop roadmap » pendant deux fronts → tâche annulée, fronts
   arrêtés, PR désarmées (`--disable-auto`), deux lignes au journal.

Chaque constat va dans `docs/harness.md` (« ce qui a tenu » / « ce qui a été coupé »), avec
l'URL des PR — c'est ce fichier, pas la spec, qui dira si trois fronts se conduisent.

### 11.10 Non vérifié, à constater à l'épreuve

- Réveil d'une session inactive à la fin d'un sous-agent d'arrière-plan **hors `/goal`** : non
  vérifié ; d'où la tâche de remplissage.
- Nom exact de l'outil de délégation (`Agent`) dans le champ `tools` d'un agent.
- Comportement d'un `CronCreate` posé par le modèle vis-à-vis des permissions ; disponibilité
  du planificateur (`CLAUDE_CODE_DISABLE_CRON`).
- Granularité du rechargement d'un frontmatter de skill (au tour suivant : constaté, non
  documenté).
- Trois worktrees `dev` **plus** trois `front` en arrière-plan : jamais tenu ; six worktrees ont
  tenu le 2026-08-29, mais sans agents superviseurs concurrents.
- `REBASE_ATTEMPTS_MAX` et `MAX_CONCURRENT_FRONTS` sont des adaptations assumées : ils se
  révisent au journal, pas dans le code sans trace.

### Arbitrages du 2026-08-30 (validation du § 11 par Jonathan)

1. La session **ouvre les fronts d'office**, en l'annonçant d'une ligne ; si le premier message
   demande autre chose, elle le traite d'abord.
2. `REBASE_ATTEMPTS_MAX = MAX_CONCURRENT_FRONTS − 1` pour les rebases mécaniques (§ 8 amendé) ;
   un seul essai pour un rebase en conflit.
3. Agent `front` : `sonnet` / `high`, à revoir au journal.
4. « pause roadmap » et « stop roadmap » existent tous deux ; « stop » désarme les `--auto`.
5. Hook `SessionStart` gardé ; retiré sans discussion s'il ne se déclenche pas au test ou s'il
   rend le démarrage collant.
6. `ROADMAP_REFILL_INTERVAL = 10 min`, à confirmer à l'épreuve.
7. Épreuve sur deux issues de documentation.
