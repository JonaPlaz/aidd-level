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
  jamais un ordre. Il ne lance rien. Délai `ROADMAP_SCAN_TIMEOUT = 20 s` (adaptation assumée :
  le défaut documenté de 600 s est inacceptable au démarrage ; 20 s couvre le `fetch` borné
  ci-dessous plus quelques appels `gh`) ; dépassement, `gh` non authentifié, dépôt absent →
  **sortie vide, exit 0**, le démarrage n'est jamais cassé.
- **Tout scan commence par un `git fetch origin main` borné** à `FETCH_TIMEOUT = 10 s`
  (`timeout 10 git fetch --quiet origin main`) : sans lui, `origin/main` date de la dernière
  commande de Jonathan et la condition 6 du § 11.3 juge sur un état mort. Le `fetch` ne
  modifie aucun checkout (il n'écrit que des références distantes), il est donc le seul geste
  git que le script et la session s'autorisent. Échec ou dépassement → **frein « état distant
  inconnu »** : le tableau s'affiche, la liste retenue est vide, rien ne s'ouvre.
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
        ├─► verrou `roadmap-<horodatage>` — refusé si un autre existe : on sort sans rien faire
        ├─┐ fenêtre de sélection, sous ce verrou :
        │ ├─► git fetch origin main (borné FETCH_TIMEOUT)
        │ ├─► node .claude/hooks/roadmap-ready.js  → freins, fronts prêts, créneaux libres
        │ └─► réservation : feature-lock.js lock <n°> pour chaque front retenu
        ├─► `roadmap-<horodatage>` RETIRÉ (fin de la fenêtre — quel que soit le chemin)
        ├─► pour chaque front réservé (≤ MAX_CONCURRENT_FRONTS) : un sous-agent `front`,
        │   lancé explicitement EN ARRIÈRE-PLAN
        │     └─► skill `feature` préchargé : agent `dev` (worktree) → PR
        │           → attente revue Codex → passe de correction → réponses tracées
        │           → rebase → `gh pr merge --auto` → journal → unlock <n°>
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
2. **Ses dépendances sont mergées — l'état vient de GitHub, pas de `ROADMAP.md`.** Le graphe
   (colonnes « dépend de », « Sorties », « Issue ») se lit dans `ROADMAP.md`, seul endroit où
   il existe ; **l'état, lui, se lit sur GitHub**. Motif, remarque Codex sur la PR #48 : la
   ligne d'état d'un chantier n'est ajoutée qu'à la PR suivante (§ 4 du skill `feature`), donc
   un dépendant resterait inéligible pendant tout l'intervalle — le harnais s'auto-bloquerait
   sur son propre retard d'écriture. Prédicat, pour chaque numéro cité en « dépend de » :
   - son issue (colonne « Issue » de sa dernière ligne qui en déclare une) est **`CLOSED`** et
     porte **au moins une PR `MERGED`** dans
     `gh issue view <n°> --json state,closedByPullRequestsReferences` ;
   - **repli** quand aucune issue n'est connue pour ce chantier (lignes 0, 15, 16 : colonne
     `—`) : l'état de sa **dernière ligne** de `ROADMAP.md` contient `mergé` — `ROADMAP.md`
     étant append-only, la dernière ligne fait foi, et ses « Sorties » sont celles de sa
     dernière ligne qui en déclare (les lignes d'état écrivent `—`).
   - une issue fermée **sans** PR mergée (fermée à la main, doublon) ne vaut **pas** mergée :
     l'écart est imprimé, le dépendant reste écarté.
   Conséquence assumée : `ROADMAP.md` redevient ce qu'il est, l'historique humain ; une ligne
   d'état en retard ne bloque plus personne, et le § 4 du skill `feature` (ligne ajoutée dans
   la PR suivante ou une PR `docs:`) n'est pas modifié par ce chantier.
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

**Freins globaux** — chacun vide la liste retenue et se dit dans la sortie ; aucun n'ouvre quoi
que ce soit :

1. **`blocked` quelque part** : une **PR ouverte** *ou* une **issue ouverte** portant le label
   `blocked` (`gh pr list --state open --label blocked`, `gh issue list --state open --label
   blocked` — la première suffit à freiner). Le prédicat est unique et couvre les deux, par
   cohérence avec le § 11.6.3 : le quota Codex se pose sur la PR, un cadrage impossible se pose
   sur l'issue, et les deux doivent geler la file.
2. **Pause déclarée** : le marqueur `<git common dir>/roadmap-paused` existe (§ 11.7).
3. **État distant inconnu** : le `git fetch origin main` borné a échoué (§ 11.1).

C'est un état lisible de `gh` et du disque, **sans mémoire à tenir entre deux invocations** :
un tir de la tâche de remplissage repart de zéro et retrouve les mêmes freins.

Cas dégradés, tous silencieux et sans ouverture : ligne de roadmap illisible ou absente pour
une issue ; « dépend de » citant un chantier inconnu ; dépendance sans issue ni ligne `mergé` ;
issue fermée sans PR mergée ; deux lignes contradictoires (la dernière gagne, l'écart est
signalé dans la sortie) ; `gh` en échec ou hors ligne ; dépôt git absent. Le script **ne bloque
jamais** et n'écrit rien hors de son marqueur de pause : il imprime, exit 0.

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
pour son issue, exactement le cycle du § 3 — à ceci près que **son verrou par numéro a déjà été
réservé par la sélection** (§ 11.5, sélection atomique) : il le trouve posé, ne le repose pas,
et c'est lui qui le **retire** à son pas terminal. Puis agent `dev`, PR, attente de la revue
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
| `background` | **`true`, explicitement.** Exigence, pas un défaut : la doc *sub-agents* décrit `background` comme le champ de frontmatter qui « garde un sous-agent en arrière-plan même quand Claude veut le résultat », et le comportement par défaut est **non vérifié** (les deux lectures de la page divergent). Un front lancé au premier plan bloquerait la session pendant toute sa boucle de revue et supprimerait le parallélisme : le skill demande en plus, à chaque appel, un lancement en arrière-plan, et l'épreuve du § 11.9 échoue si la session n'est pas rendue à Jonathan dans la foulée |

**Le `front` ne touche jamais au checkout principal.** Les opérations git sur une branche
restent au propriétaire du checkout (§ 3) : c'est l'agent `dev`, relancé dans son worktree.
Motif : l'incident du chantier 11 (`docs/harness.md`, « un commit a atterri sur `main` local
pendant qu'un fond changeait de branche ») deviendrait la norme à trois fronts. Deux garde-fous :

- `roadmap` pose un verrou `roadmap-<horodatage>` **le temps de la sélection** (§ 11.2) ;
- **extension de `guard-git`** : dès que **plus d'un verrou** est présent, un `git checkout`,
  `switch`, `rebase`, `merge`, `commit` ou `push` dont le `cwd` est le **checkout principal**
  (`git rev-parse --git-dir` == `--git-common-dir`) est refusé (exit 2). Et un verrou
  `roadmap-*` **n'autorise aucune branche** à `gh pr create`, comme `trivial-*` n'autorise que
  `trivial/…`.

**Sélection atomique.** `feature-lock.js lock` est **idempotent** : il réécrit le fichier sans
rien signaler. Il réserve, il n'exclut pas — deux invocations simultanées de `/roadmap`
(un tir de la tâche de remplissage pendant que Jonathan en lance une) verrouilleraient toutes
deux le même numéro et ouvriraient deux fronts dessus. L'exclusion est donc portée par le
verrou `roadmap-<horodatage>`, avec ces règles :

- il est posé **avant** le `fetch` et tenu **jusqu'à la réservation** des verrous par issue
  incluse ; toute la fenêtre « je lis l'état / je décide / je réserve » est sous ce verrou ;
- une invocation qui trouve **un verrou `roadmap-*` déjà présent sort immédiatement**, sans
  scanner, sans ouvrir, en le disant — elle ne « remplit pas les créneaux libres » au passage :
  le tir suivant le fera dix minutes plus tard ;
- il est **retiré sur chaque chemin terminal**, sans exception : file vide, aucun front
  ouvrable, frein global, pause, stop, annulation de la tâche, erreur de scan, dépassement de
  `ROADMAP_SCAN_TIMEOUT`. Le propriétaire est **l'invocation de `/roadmap` qui l'a posé**, et
  elle le retire elle-même — jamais un front, jamais la session à sa place. Un verrou
  `roadmap-*` trouvé au démarrage d'une session (invocation morte en route) est signalé au
  journal et retiré : il n'a plus de propriétaire vivant. Sans cette règle, l'épreuve « plus
  aucun verrou à la fin » (§ 11.9) serait insatisfaisable.

Le verrou `roadmap-*` ne dure donc que quelques secondes : le garde-fou de checkout ci-dessus
repose sur les **verrous par numéro**, pas sur lui — deux fronts ouverts, deux verrous, checkout
principal en lecture seule.

**La session, elle**, ne fait rien de git (hors le `fetch` borné du § 11.1) ni de `gh` pendant
l'attente. Elle : annonce en une ligne les fronts ouverts ; répond à Jonathan s'il demande
autre chose ; à chaque retour de front (le résultat d'un sous-agent d'arrière-plan « parvient à
Claude comme une notification d'achèvement dans un tour ultérieur », doc *sub-agents*),
**rappelle `/roadmap`** pour remplir le créneau libéré.

**Un seul propriétaire de la ligne de journal par front : le front lui-même.** Il l'écrit à son
pas terminal (mergé, `blocked`, borne atteinte), avec pointeur — PR et SHA (§ 6). **La session
n'ajoute rien** : elle ne sait du front que ce qu'il lui a rapporté, une ligne écrite depuis
elle serait un doublon sans pointeur propre. Ce qui s'y ajoute mécaniquement, et qui n'est pas
un doublon : le hook `journal.js` écrit **une ligne par fin de sous-agent** sur `SubagentStop`
(`agent terminé : <type>`, avec branche et SHA courts). Vérifié dans `.claude/hooks/journal.js`
le 2026-08-30 : la condition « hors `main` avec travail non committé » ne s'applique **qu'à**
l'événement `Stop` ; `SubagentStop` écrit inconditionnellement. Les deux lignes se distinguent
par leur colonne acteur (`hook journal.js (SubagentStop)` contre le front) : la première dit
qu'un agent s'est arrêté, la seconde dit ce qu'il a produit. Aucune modification de
`journal.js` n'est demandée par ce chantier ; à trois fronts, elle produit six lignes
mécaniques (trois `front`, trois `dev`), c'est le prix de la trace.

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
3. **`blocked` n'est jamais levé par un agent** : une issue **ou** une PR ouverte étiquetée
   `blocked` gèle l'ouverture de tout nouveau front jusqu'à un geste de Jonathan (frein global
   1 du § 11.3 — même prédicat des deux côtés).
4. **Quota Codex** : un front qui lit un message de quota épuisé pose `blocked`, journalise et
   s'arrête (§ 3, inchangé) ; le frein global (§ 11.3) empêche alors les autres fronts de
   partir brûler ce qu'il reste. Un front déjà lancé va au bout de sa passe.
5. `REVIEW_WAIT_MAX = 20 min` par front, inchangé.
6. **Rebase en cascade** — amendement au § 8 : « une tentative de rebase, puis `blocked` » vaut
   pour un rebase **en conflit** — un seul essai, inchangé, c'est là que le jugement humain
   manque. Un rebase **mécanique** (sans conflit), parce qu'un autre front a mergé entre-temps,
   se rejoue, et le front **compte ses rebases mécaniques réels** jusqu'à
   `REBASE_MECHANICAL_MAX = 6`, puis `blocked`. La borne `MAX_CONCURRENT_FRONTS − 1` posée au
   premier jet est **fausse** (remarque Codex sur la PR #48) : avec le re-remplissage, la file
   se recharge, et un front lent peut être doublé par bien plus de N − 1 pairs. Origine de 6 :
   `2 × MAX_CONCURRENT_FRONTS`, soit deux vagues complètes de fronts — au-delà, la branche est
   manifestement trop lente pour la file et la faire tourner encore ne fait qu'occuper un
   créneau. **Adaptation assumée**, révisée au journal ; le compteur est celui du front,
   remis à zéro à chaque front, et le nombre de rebases effectués va dans sa ligne de journal.
   Sans cet amendement, ouvrir trois fronts produit mécaniquement deux `blocked` à
   l'intégration.
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
- **Trois mots**, reconnus par la session (instruction du skill et d'`AGENTS.md § Flow d'une
  PR`, pas un hook — aucun hook ne peut arrêter un sous-agent) :
  - « **pause roadmap** » : plus aucun front nouveau, tâche de remplissage annulée ; les fronts
    en cours vont au bout (leur travail est déjà dans une PR) ;
  - « **stop roadmap** » : idem, plus `TaskStop` sur chaque front en cours et
    `gh pr merge <n> --disable-auto` sur les PR armées — sinon une PR se merge après l'arrêt.
    Les worktrees gardent leur travail (§ 7). Une ligne de journal par front arrêté ;
  - « **reprends la roadmap** » : la seule façon de repartir.
- **La pause survit à la session.** Une pause qui ne vivrait que dans le contexte serait perdue
  au `/clear`, au redémarrage, ou au premier tir de la tâche de remplissage d'une autre session
  — Jonathan aurait dit « pause » et la file repartirait dans son dos. Elle s'écrit donc sur le
  disque : `node .claude/hooks/roadmap-ready.js pause` crée
  **`<git common dir>/roadmap-paused`** (même répertoire que `feature-locks/`, hors de l'index,
  visible de tous les worktrees), horodaté et portant le mot qui l'a posée (`pause` ou `stop`).
  Il est lu par `roadmap-ready.js` (frein global 2, § 11.3) **et** par le skill, qui sort sans
  rien ouvrir — y compris quand il est appelé par la tâche planifiée. « reprends la roadmap »
  exécute `roadmap-ready.js resume`, qui retire le marqueur ; Jonathan peut aussi le supprimer
  à la main. Le hook `SessionStart` affiche « roadmap en pause depuis <horodatage> » à la place
  du tableau : une pause oubliée se voit au démarrage suivant.
- **La reprise en main documentée** : `/goal <condition>` (« … jusqu'à ce que la file d'issues
  `to-implement` soit vide ») ou `/loop 10m /roadmap`, tapés par lui, si un jour la tâche
  planifiée ne suffit pas. Le skill imprime la ligne prête à coller dans son compte rendu.

### 11.8 Sorties du chantier

| Fichier | Rôle |
|---|---|
| `.claude/skills/roadmap/SKILL.md` | le skill, invocable par le modèle, `argument-hint: [--dry-run]` |
| `.claude/hooks/roadmap-ready.js` | la règle de maturité, seule implémentation ; `fetch` borné puis lecture de `gh` et de `ROADMAP.md` ; imprime freins, fronts prêts, retenus, à spécifier. Sous-commandes `pause` / `resume` / (défaut) `scan` ; seule chose qu'il écrit : le marqueur `roadmap-paused` |
| `.claude/hooks/tests/roadmap-ready.test.js` | tests unitaires, exécutés par la CI (comme `guard-git.test.js`) |
| `.claude/hooks/guard-git.js` | extension : checkout principal en lecture seule pour git dès qu'il y a plus d'un verrou ; verrou `roadmap-*` n'autorisant aucune branche |
| `.claude/agents/front.md` | l'agent qui tient un cycle `feature` complet, en arrière-plan |
| `.claude/settings.json` | hook `SessionStart`, `allow` de `roadmap-ready.js` |
| `AGENTS.md` § Flow d'une PR | trois lignes : `/roadmap` ouvre les fronts prêts, `feature` reste le cycle unitaire, « pause / stop roadmap » |
| `.claude/skills/feature/SKILL.md` | une phrase : lancé par un agent `front`, il ne travaille jamais dans le checkout principal |
| `ROADMAP.md` | une ligne ajoutée : `| 17 | Lancement autonome de la roadmap | 08 § 11 | 16 | .claude/skills/roadmap/, .claude/hooks/, .claude/agents/front.md, AGENTS.md | #46 | à faire |` |

Ce chantier ne touche ni `src/` ni `tests/` : aucune décision de scoring, aucun seuil de
domaine. La règle 7 d'AGENTS.md (domaine + infrastructure) est sans objet.

### 11.9 Tests / épreuve

**Unitaire** (`roadmap-ready.test.js`, en CI — la règle de maturité est du code, elle se teste
comme `guard-git`). Chaque cas est un `ROADMAP.md` de fixture plus un état `gh` injecté par
fichier JSON, jamais par le réseau :

1. dépendance dont l'issue est ouverte → front écarté ;
2. dépendance dont l'issue est `CLOSED` avec une PR `MERGED`, **et dont la ligne d'état
   `ROADMAP.md` manque encore** → dépendance satisfaite : c'est le cas que la première version
   ratait ;
3. dépendance dont l'issue est `CLOSED` **sans** PR mergée → écarté, écart imprimé ;
4. dépendance sans issue (colonne `—`), deux lignes pour le même chantier, la dernière dit
   `mergé` → satisfaite par le repli (règle « la dernière ligne gagne ») ;
5. sorties partagées avec un front déjà ouvert → écarté ;
6. verrou `feature-locks/<n°>` présent → écarté ;
7. PR ouverte portant le numéro, ou `Closes #<n°>` dans le corps → écarté ;
8. spec absente de `origin/main` → classé « à spécifier », jamais retenu ;
9. une PR ouverte `blocked` → **liste retenue vide**, frein annoncé ; **idem pour une issue
   ouverte `blocked`** (les deux cas sont testés séparément) ;
10. marqueur `roadmap-paused` présent → liste vide, frein « pause » ; `resume` le retire et la
    liste revient ;
11. `git fetch` en échec ou dépassant `FETCH_TIMEOUT` → liste vide, frein « état distant
    inconnu » ;
12. cinq fronts prêts → **trois** retenus, dans l'ordre croissant ;
13. `gh` en échec, `ROADMAP.md` illisible, hors dépôt git → sortie vide, exit 0.

Deux cas se testent hors du script, à l'épreuve, parce qu'ils portent sur la conduite du skill :
un verrou `roadmap-*` présent → la seconde invocation sort sans scanner ; toute sortie du skill
(y compris frein et erreur) → plus aucun verrou `roadmap-*`.

**Épreuve de bout en bout** — la preuve demandée par l'issue #46 : **deux issues prêtes, deux
fronts ouverts, deux PR mergées sans geste humain.**

1. Deux issues `to-implement`, specs déjà sur `origin/main`, sorties disjointes (au besoin, deux
   chantiers de documentation).
2. Jonathan ouvre la session et écrit **un message quelconque** (l'épreuve échoue s'il a dû
   taper `/roadmap`, `/feature`, `/goal` ou `/loop`).
3. Attendu, sans autre geste : **la session rendue à Jonathan dans la foulée** (preuve que les
   fronts tournent en arrière-plan) ; deux sous-agents `front` en cours ; deux PR ouvertes avec
   label `to-review` ; deux verrous par numéro présents pendant le run et **aucun verrou
   `roadmap-*` au-delà de la sélection**, **plus aucun verrou du tout à la fin** ; deux PR
   `mergedAt` non nul, mergées en squash, branches supprimées ; chaque commit portant le
   trailer `Co-Authored-By: Claude <noreply@anthropic.com>` ; une ligne de journal par front
   avec pointeur (PR, SHA).
4. Preuve du plafond : quatre issues prêtes → trois fronts, le quatrième ouvert seulement après
   un merge, par un tir de la tâche de remplissage (visible au journal : l'horodatage du
   quatrième front est postérieur au `mergedAt` du premier).
5. Preuve du frein : poser `blocked` sur une PR ouverte, invoquer `/roadmap` → rien n'est
   ouvert, le motif est dit ; recommencer avec le label sur une **issue** ouverte.
6. Preuve du garde-fou git : depuis le checkout principal, avec deux verrous présents, tenter
   `git checkout -b x` → refus du hook (test de déclenchement par violation, § 4).
7. Preuve du mot d'arrêt : « stop roadmap » pendant deux fronts → tâche annulée, fronts
   arrêtés, PR désarmées (`--disable-auto`), deux lignes au journal.
8. Preuve de la pause persistante : « pause roadmap », puis `/clear` **et** redémarrage de la
   session → le hook `SessionStart` annonce la pause, un tir de la tâche de remplissage n'ouvre
   rien ; « reprends la roadmap » → le marqueur disparaît et la file repart.
9. Preuve de la ligne de journal : une ligne du front par PR, avec PR et SHA, **plus** les
   lignes mécaniques de `journal.js` sur `SubagentStop` — et aucune ligne écrite par la session.

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
- Comportement par défaut d'un sous-agent (premier plan ou arrière-plan) : **non vérifié**, la
  page *sub-agents* se lit dans les deux sens ; d'où le `background: true` explicite.
- `REBASE_MECHANICAL_MAX`, `MAX_CONCURRENT_FRONTS`, `FETCH_TIMEOUT` et
  `ROADMAP_SCAN_TIMEOUT` sont des adaptations assumées : elles se révisent au journal, pas dans
  le code sans trace.

### Historique des arbitrages du § 11

Le texte ci-dessus fait foi ; cette table dit seulement d'où viennent ses décisions.

| Arbitrage | Où il a atterri |
|---|---|
| 2026-08-30, Jonathan : la session **ouvre les fronts d'office**, en l'annonçant d'une ligne ; si le premier message demande autre chose, elle le traite d'abord | § 11.1, § 11.5 |
| 2026-08-30, Jonathan : agent `front` en `sonnet` / `high`, à revoir au journal | § 11.5 |
| 2026-08-30, Jonathan : « pause » **et** « stop » ; « stop » désarme les `--auto` | § 11.7 |
| 2026-08-30, Jonathan : hook `SessionStart` gardé, retiré s'il ne se déclenche pas au test ou rend le démarrage collant | § 11.1 |
| 2026-08-30, Jonathan : `ROADMAP_REFILL_INTERVAL = 10 min`, à confirmer à l'épreuve | § 11.5 |
| 2026-08-30, Jonathan : épreuve sur deux issues de documentation | § 11.9 |
| 2026-08-30, Codex (PR #48) : la maturité d'une dépendance se lit sur GitHub, pas dans la ligne d'état de `ROADMAP.md`, en retard par construction | § 11.3 condition 2 |
| 2026-08-30, Codex (PR #48) : le frein `blocked` couvre les issues autant que les PR | § 11.3 frein 1, § 11.6.3 |
| 2026-08-30, Codex (PR #48) : la pause doit survivre à la session → marqueur sur disque, mot de reprise | § 11.7 |
| 2026-08-30, Codex (PR #48) : `MAX_CONCURRENT_FRONTS − 1` faux avec re-remplissage → `REBASE_MECHANICAL_MAX = 6`, un seul essai en conflit (remplace l'arbitrage du même jour) | § 11.6.6 |
| 2026-08-30, Codex (PR #48) : le verrou `roadmap-*` se retire sur chaque chemin terminal, propriétaire nommé | § 11.5 |
| 2026-08-30, Codex (PR #48) : sélection atomique — `lock` idempotent ne fait pas exclusion | § 11.2, § 11.5 |
| 2026-08-30, Codex (PR #48) : un seul propriétaire de la ligne de journal par front | § 11.5 |
| 2026-08-30, Codex (PR #48) : lancement en arrière-plan exigé, jamais supposé | § 11.5 |
| 2026-08-30, Codex (PR #48) : `git fetch origin main` borné avant tout scan | § 11.1, § 11.3 frein 3 |

## 12. Mémoire projet : source unique et arbitrages intégrés (2026-08-30)

Chantier 18, issue #49 et son commentaire. Ce § **ajoute** au § 3 (le cycle) et au § 11 (la
roadmap) ; il ne réécrit ni l'un ni l'autre. Deux règles et un constat, tous trois de conduite :
aucun seuil de domaine, aucune décision de scoring. Validé par Jonathan le 2026-08-30, réponses
comprises : le texte ci-dessous les porte, et l'historique de l'arbitrage tient en une ligne de
journal pointant l'issue #49 (§ 12.2).

### 12.1 (a) Une seule source de mémoire projet : `AGENTS.md`

Motif, dans les termes de l'arbitrage : **Codex lit `AGENTS.md` et ne résout aucun import ;
Claude Code lit les deux.** Une règle écrite du seul côté `CLAUDE.md` est donc invisible du
reviewer — c'est déjà ce qui a coûté quatre PR ouvertes hors cycle le 2026-08-30 alors que le
flux existait (journal `2026-08-30T11:30Z`, § 3 « Le cycle est imposé »).

Le partage, seul critère, appliqué ligne à ligne :

| Fichier | Ce qu'il porte |
|---|---|
| `AGENTS.md` | tout ce qui vaut **pour n'importe quel agent** : but, architecture, règles non négociables, décisions figées, où en est le projet, stack et commandes, conventions, **flow d'une PR**, définition de fini, code review rules |
| `CLAUDE.md` | `@AGENTS.md`, puis **uniquement ce qui n'existe que côté Claude Code** : hooks, skills, agents et worktrees, verrous, classifieur, valeur exacte du trailer |

**Aucune ligne présente dans les deux.** Quand une règle a une part universelle et une part
propre à Claude Code, la **règle** va dans `AGENTS.md` et la seule **valeur** dans `CLAUDE.md` —
jamais la règle deux fois. La numérotation existante d'`AGENTS.md` ne bouge pas : les règles
non négociables 1 à 7 et les points de revue 1 à 8 sont **cités par les specs** (§ 11.1 « règle 7
de la revue », § 11.8 « règle 7 d'AGENTS.md ») ; on complète une ligne, on ajoute à la fin, on ne
renumérote pas.

**Ce qui migre aujourd'hui de `CLAUDE.md` vers `AGENTS.md`** (les huit puces et le § Flow, dans
l'ordre du fichier du 2026-08-30) :

| Ligne de `CLAUDE.md` | Où elle atterrit dans `AGENTS.md` |
|---|---|
| « Lire la spec concernée … signaler l'écart et s'arrêter » | Règles non négociables, **nouvelle règle 8** (1 à 7 inchangées) |
| « Un commit touche une seule couche … le hook `guard-git` refuse domaine + infrastructure » | règle 7, **complétée** (le numéro ne bouge pas) |
| Branche courte, rebase, `--force-with-lease`, une tentative puis journaliser | Conventions, puce « Rebase sur `main` », complétée |
| `ROADMAP.md` / `docs/journal.md` append-only + « une ligne de journal sans pointeur ne vaut rien » | Conventions, puce append-only, complétée |
| La règle des entrées privées (convention existante, chemins déclarés dans `.worktreeinclude`) | **supprimée sans remplacement** : la règle y est déjà (Conventions) |
| « Les commandes du projet passent par `make` ; ne pas lancer `php`, `composer`, `vendor/bin/*` » | Stack et commandes, une phrase sous la table |
| Commits en anglais, Conventional Commits, sujet ≤ 72 caractères | Conventions, puce « Conventional Commits », complétée |
| Trailer de coauteur | **coupée en deux** : la règle et son motif (ratio 0,48 sur `profiles/self`) vont en Conventions ; la chaîne exacte reste côté Claude |
| § « Flow d'une PR — imposé par le harnais », **entier** (issue → cycle, garanties dans l'ordre, filet cron, `/roadmap`, les trois mots) | nouveau § « Flow d'une PR », placé avant « Définition de fini » |

La dernière ligne est la seule qui ait demandé une frontière : le § Flow contient depuis le
chantier 17 des puces qui nomment `/roadmap`, l'agent `front` et les trois mots de pilotage.
**Il migre entier, ces puces comprises** : elles disent ce qui arrive à une PR — qui l'ouvre, à
quelles conditions, qui l'arrête —, et c'est ce que le reviewer doit lire. Ce qui reste côté
Claude Code n'est pas la conduite mais l'**artefact** qui la porte.

**Ce qui reste dans `CLAUDE.md`** — les artefacts, pas les règles : les skills (`/feature`,
`/roadmap`) et les agents (`spec`, `dev`, `front`) invocables par la session ; les hooks
versionnés dans `.claude/settings.json` (pointeur vers le § 4, jamais leur contenu redit) ; les
worktrees ; les commandes de verrou (`feature-lock.js lock|unlock`, `roadmap-ready.js
pause|resume`) ; la chaîne exacte `Co-Authored-By: Claude <noreply@anthropic.com>` ; et **une
commande externe par appel**, parce que le classifieur refuse les commandes groupées (journal
`2026-08-28T21:23Z`, `gh repo create` + labels + protection rejouée en trois commandes) — règle
absente du fichier aujourd'hui, ajoutée ici parce qu'elle n'a pas d'autre place.

Forme cible, indicative pour la mise en page mais **normative pour le compte** (§ 12.5) : la
ligne `@AGENTS.md`, le titre, cinq puces d'au plus deux lignes — douze lignes non vides, marge
nulle. Une règle de plus n'agrandit pas `CLAUDE.md` : **elle remonte dans `AGENTS.md`**, ou une
autre en descend.

**Pointeurs à repointer dans la même PR**, sans quoi le dépôt se cite lui-même à faux :

Quatre sites citent aujourd'hui le fichier Claude comme porteur du flow ; tous doivent citer
`AGENTS.md § Flow d'une PR`. Ils sont désignés ici par leur emplacement, sans reproduire la
chaîne périmée — c'est ce qui rend l'épreuve 5 du § 12.5 lisible (sortie attendue vide).

- `AGENTS.md` › Décisions figées, colonne « Où » de la ligne Codex ;
- ce fichier, § 11.7 (les trois mots, « instruction du skill et de … ») et § 11.8 (dernière
  colonne de la table des sorties du chantier 17) ;
- `.claude/hooks/guard-git.js`, ses **deux** messages de refus — celui de `--force` et celui de
  `gh pr create` — qui renvoient le lecteur au fichier Claude. Vérifié le 2026-08-30 :
  `.claude/hooks/tests/guard-git.test.js` n'assertionne aucun de ces textes, les tests ne
  bougent pas ;
- `docs/specs/07-amorcage.md` décrit le fichier Claude du jour 1 : **non réécrit** (c'est
  l'historique de l'amorçage), une ligne de renvoi vers ce § 12 suffit.

`profiles/self/repo-context/` copie `AGENTS.md` et `CLAUDE.md` : la migration change donc le
profil que le dépôt applique à lui-même. `docs/methode.md` (l. 50-51) pose que `profiles/self/`
est **régénéré en fin de chantier** par `scripts/self-profile.py` — ce chantier ne fait pas
exception : régénération dedans, dans un commit `chore(self)` **séparé** (une couche par commit),
et l'épreuve 7 du § 12.5 vérifie que le verdict ne bouge pas.

### 12.2 (b) Une spec validée ne se contredit pas : les arbitrages sont intégrés

Constat (issue #49, PR #47) : les réponses de Jonathan aux questions ouvertes de la spec du
chantier 14 ont été **ajoutées en fin de fichier** au lieu d'être fondues dans le texte. Trois
contradictions internes ont suivi, relevées par Codex : note contre silence, `LoopThresholds`
contre `HarnessThresholds`, liste blanche incomplète. Le cycle ne disait pas **qui** intègre.

**Règle.** Une spec **validée** ne contient ni question ouverte ni section qui décide en dehors
de son texte normatif. Les questions vivent dans le **rendu** de l'agent `spec` — son dernier
message —, jamais dans le fichier.

**Le cycle, complété (§ 3 et skill `feature` § 1).** Entre « spec absente → agent `spec` →
arrêt » et « spec présente mais non committée », une étape nommée :

> **Réponses reçues → relancer l'agent `spec` avec elles → il les intègre au texte normatif et
> supprime toute question → alors seulement le commit** (branche `docs/spec-<n°>`, PR `docs:`,
> étape 3 du skill).

Le skill ne commit **jamais** une spec qui porte encore une question. Contrôle avant commit, sur
les **lignes ajoutées** aux specs par cette PR — pas le dépôt entier, pas même le fichier
entier :

```
for f in $(git diff --name-only origin/main -- docs/specs;
           git ls-files --others --exclude-standard -- docs/specs); do
  git add -N -- "$f"                                  # sans quoi une spec neuve reste invisible
  git diff -U0 origin/main -- "$f" | sed -n 's/^+//p' \
    | grep -Ei '^#{1,6} *\**(questions? ouvertes?|arbitrages?)|^[[:space:]]*[-*>]?[[:space:]]*\**(question ouverte|à trancher|à valider pa)|\?[[:space:]]*$' \
    | grep -vi historique
done
```

Toute occurrence arrête le commit. Le dernier segment du motif, une ligne qui se termine par
`?`, attrape la question ordinaire qui n'a pas la forme d'un marqueur (« Faut-il … ? ») ; le
filtre `historique` continue de s'appliquer. Trois propriétés, chacune pour une raison :

- **`git add -N` et `git ls-files --others --exclude-standard`** : le cas nominal de ce cycle est
  une spec **créée** par l'agent `spec`, donc non suivie ; un contrôle qui ne lit que
  `git diff` la laisserait passer entière — c'est précisément le fichier le plus à risque.
  `add -N` l'enregistre sans contenu, ce qui suffit à la rendre visible du diff et n'engage rien.
- **`-U0`, et seulement les lignes `+`** : le contrôle juge ce que la PR écrit, jamais ce qu'elle
  hérite. Les deux arbitrages restés ouverts depuis le 2026-08-26 dans les § 1 et § 5 de ce
  fichier ne bloquent donc que la PR qui les touche — celle qui les tranchera. Aucune exception
  nominative à tenir à jour, donc aucune liste à oublier de mettre à jour.
- **Le motif est ancré**, et filtré par `historique` : il attrape un titre de section d'arbitrage
  et une question en tête de ligne — la position d'où l'on décide —, pas une mention en cours de
  phrase, et il laisse passer la table d'historique tolérée plus bas. Sans cet ancrage, ce §
  se refuserait lui-même, puisqu'il nomme les marqueurs qu'il interdit.

**L'agent `spec`** (`.claude/agents/spec.md`, étape 4) : pose ses questions en fin de rendu,
chacune avec sa recommandation, jamais dans le fichier ; relancé avec les réponses, il les fond
là où elles décident (le § concerné, le seuil et son origine, le cas dégradé, le test qui le
fige), puis **supprime** la question ; il n'ajoute aucune section d'arbitrage.

**Ce qui reste permis** : une table d'**historique** d'arbitrages, si et seulement si elle porte
ce titre, dit qu'elle ne décide rien et que le texte fait foi, et donne pour chaque ligne
l'endroit où la décision a atterri. Forme déjà appliquée deux fois et gardée telle quelle :
`docs/specs/02-axe-harness.md` § « Arbitrages du 2026-08-30 — historique » et le § « Historique
des arbitrages du § 11 » ci-dessus. Elle **ne dispense pas** de la ligne de journal.

**L'historique va au journal**, une ligne par intégration, avec pointeur (§ 6) : étape = le
chantier, événement = « arbitrages intégrés à `docs/specs/<fichier>` », pointeur = l'issue ou la
PR où les réponses ont été données.

**`AGENTS.md` › Code Review Rules, point 9** (les points 1 à 8 sont inchangés et cités
ailleurs) :

> 9. **Une spec ne contredit pas sa propre fin** : aucune question ouverte, aucune section
>    d'arbitrage qui décide, aucun seuil ni nom de constante cité en deux valeurs différentes
>    dans le même fichier.

### 12.3 (c) Constat : le cron d'armement n'a produit aucun tir planifié

Relevé le 2026-08-30, à consigner au journal dans la PR de ce chantier.

- `auto-merge-after-codex.yml` est sur `main` depuis la PR #37, mergée à **11:38:00Z**
  (`19e2e83`) ; correctif #39 à 11:42:38Z (`e3838c8`). Son déclencheur est
  `schedule: '*/10 * * * *'` plus `workflow_dispatch` (§ « Armement par la plateforme »).
- `GET /repos/JonaPlaz/aidd-level/actions/workflows/auto-merge-after-codex.yml/runs`, lu après
  13:50Z : **`total_count = 1`**, un seul run, `event = workflow_dispatch`, 12:21:57Z,
  `success`. **Aucun run `schedule`** en plus de deux heures, là où `*/10` en prévoit une
  douzaine.
- Conséquence factuelle : le filet n'a **jamais** servi. Toutes les PR mergées depuis (#43,
  #44, #47, #48, #50, #51) l'ont été par le chemin nominal du skill — aucun armement ne peut
  être attribué au cron, puisqu'aucun tir planifié n'a eu lieu. Rien n'est resté sans suite ;
  rien ne prouve non plus que le filet fonctionne.
- Ce que dit la source (docs GitHub Actions, *events that trigger workflows*, consultée le
  2026-08-30) : « The `schedule` event can be delayed during periods of high loads of GitHub
  Actions workflow runs » ; « Scheduled workflows run on the latest commit on the default
  branch » (satisfait : le fichier est sur `main`) ; intervalle minimal 5 min (satisfait) ;
  désactivation automatique après 60 jours sans activité (sans objet, dépôt de deux jours).
- **Non vérifié** : la cause. Un délai de forte charge, un enregistrement tardif du planning
  après le merge, ou autre chose — rien dans les données relevées ne tranche.
- **Décision : pas de correctif.** C'est un filet, pas le chemin nominal, et le chemin nominal
  tient. **Prochain relevé : le 2026-08-31**, même requête ; si le nombre de runs `schedule` est
  encore nul, **ouvrir une issue** — la cause devient alors un chantier, pas un doute. Chaque
  relevé va au journal, daté et pointé ; `docs/harness.md` en héritera dans sa table
  « Prévu / Réel » quand le doute sera tranché.

### 12.4 Sorties du chantier

| Fichier | Rôle |
|---|---|
| `AGENTS.md` | absorbe les neuf entrées de la table du § 12.1 ; gagne un § « Flow d'une PR », une règle non négociable 8, un point de revue 9 ; règles 1 à 7 et points 1 à 8 non renumérotés |
| `CLAUDE.md` | réduit à `@AGENTS.md` + le titre + cinq puces d'artefacts Claude Code |
| `.claude/skills/feature/SKILL.md` § 1 | l'étape « réponses reçues → agent `spec` → intégration → commit », et le contrôle avant commit du § 12.2 |
| `.claude/agents/spec.md` | étape 4 réécrite : questions en fin de rendu avec recommandation, intégrées et supprimées à la réponse |
| `docs/specs/08-harnais.md` | ce § 12, plus les deux références repointées du § 11 |
| `docs/specs/07-amorcage.md` | une ligne de renvoi vers ce § (le texte d'amorçage n'est pas réécrit) |
| `.claude/hooks/guard-git.js` | deux chaînes de message repointées vers `AGENTS.md` |
| `profiles/self/` | régénéré par `python3 scripts/self-profile.py` (le `repo-context/` copie les deux fichiers migrés), dans un commit `chore(self)` **séparé** |
| `docs/journal.md` | deux lignes : la migration (pointeur PR), le constat cron du § 12.3 |
| `ROADMAP.md` | une ligne ajoutée, chantier 18, dépendance 17, sorties `AGENTS.md`, `CLAUDE.md`, `.claude/`, issue #49 |

Ce chantier ne touche ni `src/` ni `tests/` ni `fixtures/` : la règle 7 d'`AGENTS.md` (domaine +
infrastructure dans un même commit) est sans objet, et **aucun seuil ni décision de scoring n'est
touché** — ce qui bouge dans `profiles/self/`, c'est la matière lue, pas la règle qui la lit. Il
**dépend du chantier 17** (PR #51, mergée 2026-08-30T13:50:27Z), qui a écrit les trois dernières
puces du § Flow que ce chantier déplace.

### 12.5 Tests / épreuve

1. **`CLAUDE.md` tient en douze lignes.** `grep -c '[^[:space:]]' CLAUDE.md` ≤ **12** (lignes non
   vides, `@AGENTS.md` et titre compris). Origine : consigne de Jonathan du 2026-08-30 ; la
   forme cible du § 12.1 en produit exactement douze. **Marge nulle**, et c'est voulu : un
   dépassement n'est pas un dépassement de format, c'est le signal qu'une règle a été écrite du
   mauvais côté — elle remonte dans `AGENTS.md`.
2. **Aucune duplication entre les deux fichiers.** Comparaison mécanique des lignes normalisées
   (minuscules, marqueurs markdown et ponctuation retirés, espaces réduits) :

   ```
   norm() { sed -E 's/^[[:space:]]*[-*][[:space:]]*//; s/[`*_#|]//g; s/[[:punct:]]+/ /g; \
            s/[[:space:]]+/ /g; s/^ //; s/ $//' "$1" | tr 'A-Z' 'a-z' | grep -v '^$' | sort -u; }
   comm -12 <(norm AGENTS.md) <(norm CLAUDE.md)
   ```

   Sortie attendue : **vide**. Le test attrape le copier-coller, pas la paraphrase : la
   paraphrase est du ressort de la revue (point 9 et point 8 « signaler ce qui contredit les
   specs »). Jetons de contrôle, chacun présent d'**un seul** côté : `append-only`,
   `Conventional Commits`, `--force-with-lease`, `to-review`, `vendor/bin`, `72`. Un **nom
   d'artefact**
   (`/feature`, `guard-git`, `spec`) peut apparaître des deux côtés : c'est une règle qui ne se
   répète pas, pas un nom.
3. **Une spec de test qui pose une question est refusée.** Sur une PR jetable (`--trivial`,
   § 9), ajouter à une spec une ligne « **Question ouverte** : … ». Attendu, dans l'ordre :
   le contrôle avant commit du § 12.2 la refuse **avant** la PR ; contrôle levé à la main, la
   revue (Codex sur le point 9, ou Jonathan) la signale. Réserve honnête : la seconde moitié
   n'est pas déterministe — Codex est un modèle, il peut passer à côté. Le point 9 reste donc
   **un point de revue**, et il ne bascule en `grep` de CI que si cette épreuve échoue : le
   constat va au journal, et la bascule est alors un chantier à part.
4. **Le cycle complet passe encore.** Une spec nouvelle écrite par l'agent `spec` : ses
   questions sont **dans le rendu**, le fichier committé n'en contient aucune, et le journal
   porte la ligne « arbitrages intégrés » avec son pointeur.
5. **Les pointeurs ne mentent plus** — un `grep` par site, aucune exclusion en bloc :

   | Site | Commande | Attendu |
   |---|---|---|
   | Tout le dépôt, chaîne périmée | `grep -rn 'CLAUDE\.md § Flow' AGENTS.md docs .claude` | **vide** (le § 12.1 désigne ses sites sans reproduire la chaîne) |
   | Ce fichier, § 11.7 et § 11.8 | `grep -n 'CLAUDE\.md' docs/specs/08-harnais.md` | aucune ligne dans le § 11 ; celles du § 12 parlent du fichier, pas du flow |
   | `guard-git.js`, message `--force` | `grep -n '(CLAUDE\.md)' .claude/hooks/guard-git.js` | **vide**, et `grep -c '(AGENTS\.md)'` = 1 |
   | `guard-git.js`, message `gh pr create` | `grep -n 'CLAUDE\.md § Flow' .claude/hooks/guard-git.js` | **vide**, et le message cite `AGENTS.md § Flow d'une PR` |
   | Spec d'amorçage | `grep -n '08 § 12' docs/specs/07-amorcage.md` | **une** ligne (le renvoi ajouté ; le texte du jour 1 reste) |
   | `AGENTS.md` › Décisions figées | `grep -n "Flow d'une PR" AGENTS.md` | au moins deux lignes : le titre du § migré et la colonne « Où » de la ligne Codex |
6. **Rien n'a bougé côté scoring** : `make test` et `make demo` inchangés, `docs/calibration.md`
   toujours vrai (le chantier ne touche aucun fichier de `src/`, `tests/` ou `fixtures/`).
7. **Le dépôt se note comme avant.** Après `python3 scripts/self-profile.py`, `make evaluate
   self` rend le **même verdict qu'au chantier 11 : Blue, plafonné par Taille** (`docs/methode.md`
   l. 50-51). Un verdict différent n'est pas un échec en soi — il se journalise, avec l'axe qui a
   bougé et le pointeur du fichier de `repo-context/` en cause — mais il **arrête la PR** : une
   migration de mémoire projet n'a aucune raison de déplacer un niveau, et si elle le fait, c'est
   la mesure qu'il faut regarder avant de merger.

Ligne à ajouter à `ROADMAP.md` (append-only, une ligne, colonnes de la table) : chantier **18**,
« Source unique `AGENTS.md`, arbitrages intégrés aux specs », spec « 08 § 12 (2026-08-30) »,
dépend de « 17 », sorties « `AGENTS.md`, `CLAUDE.md`, `.claude/skills/feature/`,
`.claude/agents/spec.md` », issue « #49 », état « à faire ».
