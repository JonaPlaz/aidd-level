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

## Ratio absent — jamais coercé en zéro

Ajouté le 2026-08-30 (chantier 13). `commits.ai_coauthored_ratio` n'entre dans cet axe que
pour départager « prompts » de « rien », quand `agents_md = false` et tous les compteurs à
zéro. Un ratio **absent** (`null`) n'est pas un ratio nul : dans ce cas, règle commune de la
spec 05 § *Signal absent*, mais avec le plafond réellement observable — `agents_md` et les
compteurs sont lus et connus, seul le bas de l'axe est indécis :

- verdict `Range(White, Red, 0)` ;
- note pointée « ratio absent : impossible de départager prompts de rien »
  (`git-activity.json › commits.ai_coauthored_ratio = absent`).

Le filtre White du use case (spec 05 § Filtre) traite déjà `null` comme « non nul » ; l'axe
s'aligne. Test : `agents_md = false`, compteurs 0, ratio `null` → fourchette [White, Red] et
note ; ratio `0` → White sans note (0 est une valeur).

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

`context_files.agents_md` absent (`null`) → `Range(White, Gold, 0)` + note pointée « = absent »
(spec 05 § *Signal absent* ; jamais coercé en `false`) · `perceval` → prompts/Red · `bohort` → context eng./Blue · `leodagan` et `arthur` →
behavior/Copper · fixture avec Makefile `retry` borné → boucles/Gold · fixture `arthur` sans
`repo-context/` → Copper + note · fixture ratio 0 et compteurs 0 → White.

## Boucles — détection resserrée (chantier 14, 2026-08-30)

Ajouté le 2026-08-30 (issue #45). Constat de la revue : `LoopDetector` promeut à Gold **tout**
fichier éligible qui contient un motif de relance quelque part et un motif de borne ailleurs
dans le même fichier. Un script de 300 lignes avec un `while` en tête et le mot `budget` à la
fin devient une « boucle bornée ». Aucun profil fourni n'a de boucle : la règle n'a jamais été
éprouvée sur du réel.

Ce que ce chantier **ne** touche **pas** : la valeur « boucles » de la grille et sa condition
(relance **et** borne, § *Boucles* ci-dessus), le rattachement Behavior/Context engineering,
le cumul des valeurs, les seuils des trois autres axes, `HarnessThresholds::AI_RATIO_NONE`, la
règle du § *Ratio absent*, les notes existantes (« boucles : aucune relance bornée trouvée »,
« boucles non observables : repo-context/ absent »). Aucun seuil existant ne change de valeur.

### 1. Proximité — une fenêtre de lignes, pas un bloc

Une relance et une borne ne comptent que si elles sont **à portée l'une de l'autre** :

```
|ligne(relance) − ligne(borne)| ≤ LoopThresholds::PROXIMITY_LINES
```

Les deux constantes de ce chantier vivent dans une classe dédiée `LoopThresholds`, **jamais**
dans `HarnessThresholds` : elles ne décident aucune valeur de la grille, elles paramètrent la
seule détection de boucle. Elle se place **à côté de `LoopPatterns`**, qu'elle sert —
`src/Domain/Axis/Harness/LoopThresholds.php`, et non `src/Domain/Threshold/` où vivent les
seuils qui décident un niveau (arbitrage du 2026-08-30, remarque Codex sur la PR #47). Le
nommage `*Thresholds` et la règle 2 (une constante nommée, sa justification à côté) sont
respectés dans les deux cas. `HarnessThresholds` garde `AI_RATIO_NONE` et rien d'autre.

| Constante | Valeur | Origine |
|---|---|---|
| `LoopThresholds::PROXIMITY_LINES` | 10 | **adaptation assumée**, calibrée sur les trois formes réelles vérifiables ci-dessous, avec marge |
| `LoopThresholds::COUNTED_MAX` | 20 | reprise **telle quelle** du § *Boucles* d'origine (« entier ≤ 20 associé »), jamais sourcée (§ 4) |

Ce qu'il faut couvrir, revérifié à la source le 2026-08-30 :

- une étape GitHub Actions `nick-fields/retry` (doc de l'action, transférée de
  `nick-invision/retry` le 2022-02-15) : le token `retry` est sur la ligne `uses:`, et
  `max_attempts` est **une clé de `with:` parmi treize** (`timeout_minutes`,
  `retry_wait_seconds`, `retry_on`, `shell`, `polling_interval_seconds`…) ; elle peut donc
  arriver plusieurs lignes plus bas — jusqu'à ~8 lignes dans une étape entièrement paramétrée ;
- une relance shell à compteur : `n=0`, puis `until … do`, puis le test de borne
  (`[ "$n" -ge 3 ]`) — deux à six lignes selon l'écriture ;
- GitLab CI (`.gitlab-ci.yml`, doc vérifiée le 2026-08-30) écrit `retry:` puis `max:` sur la
  ligne suivante.

10 laisse la marge de ces trois formes et **ne laisse pas passer** le cas de l'issue (deux
tokens à 200 lignes d'écart). C'est une borne de sécurité, pas une mesure : aucune campagne
sur un corpus réel de dépôts ne l'a établie (§ 8).

Règles d'application :

- **ordre indifférent** : la borne se déclare souvent *avant* la boucle
  (`MAX_ATTEMPTS=3` puis `until`), c'est une valeur absolue ;
- **0 autorisé** : relance et borne sur la même ligne (`for i in $(seq 1 3)`,
  `until [ $n -ge 3 ]`) ;
- **première paire trouvée** : les lignes sont parcourues dans l'ordre du fichier, les
  fichiers dans l'ordre de `RepoContext` (`RepoContextReader` trie les chemins) — la sortie
  est déterministe, la preuve cite toujours la même paire.

**Pourquoi pas « même bloc »** : un bloc n'existe pas de la même façon en YAML (indentation),
en Make (recette tabulée), en shell (`do`/`done`), en JS (accolades) et en Python
(indentation) — le trancher demanderait un analyseur par langage dans `src/Domain/`, qui doit
rester du calcul pur et sans dépendance (règle 1). La fenêtre de lignes est indépendante du
langage, et surtout **elle se relit** : la preuve rendue cite les deux numéros de ligne, le
lecteur ouvre le fichier et vérifie (§ 5).

### 2. Un commentaire n'est ni une relance ni une borne — ligne **et** bloc

Le fichier est parcouru **ligne à ligne, avec un état de bloc** ; les numéros de ligne des
lignes conservées ne bougent jamais — la preuve cite la ligne réelle du fichier.

| État | Déclencheur | Effet |
|---|---|---|
| commentaire de ligne | premier caractère non blanc `#` ou `//` | la ligne entière est ignorée |
| **ouverture de bloc** | la ligne contient `/*` hors état de bloc | la ligne est ignorée **et** l'état passe à « dans un bloc » |
| **dans un bloc** | — | **chaque ligne est ignorée**, quel que soit son contenu (une ligne de corps sans `*` en tête comprise) |
| **fermeture de bloc** | la ligne contient `*/` | la ligne est ignorée et l'état revient à « hors bloc » |
| bloc jamais fermé | fin de fichier atteinte en état bloc | tout le reste du fichier est ignoré |

Remarque Codex sur la PR #47 : un filtrage ligne à ligne fondé sur le seul premier caractère
laissait cherchable le **corps** d'un `/* … */` multi-lignes (`retry` en ligne 2 d'un bloc,
`max_attempts` en ligne 3, aucune des deux ne commençant par `*`) — exactement le faux positif
que ce chantier prétend fermer. L'état de bloc supprime la classe entière ; le préfixe `*`
n'est plus une règle en soi, il est couvert par l'état.

Ce suivi d'état est un **automate à deux états** (hors bloc / dans un bloc), pas un analyseur
syntaxique : il ne contredit pas le refus du « même bloc » du § 1, qui demanderait, lui, de
connaître la structure de cinq langages.

Trois limites explicites, toutes du côté du **sur-filtrage** (faux négatif possible, jugé moins
grave qu'un faux positif) : `/*` ouvert **à l'intérieur d'une chaîne de caractères** ouvre quand
même un bloc ; du code placé **avant** `/*` sur la même ligne est perdu avec elle ; les
docstrings Python `""" … """` ne sont **pas** traitées (**non vérifié** : aucune forme de
relance Python n'est détectée par ailleurs, § 8).

Justification : le § *Boucles* ci-dessus pose déjà « preuve structurelle ≠ preuve
fonctionnelle : un hook déclaré n'est pas un hook qui tourne ». Un commentaire qui *parle* de
relance est du déclaratif dans un fichier de code, et le déclaratif n'entre dans aucun calcul
(règle 4). Une borne en commentaire (`# max_attempts=3`) à côté d'une relance non bornée est
une intention, pas un cap : la relance reste non bornée, donc un risque, pas une boucle.

Conséquence sur l'existant : la fixture de test maison
`until ./run.sh; do echo retrying; done` + `# max_attempts=3` **cesse** d'être une boucle ;
elle doit être réécrite en forme exécutable (§ 7, TP1). C'est un test maison, aucun profil
fourni n'est concerné.

Limite assumée : un commentaire **en fin de ligne de code** (`until cmd; do :; done # 3 max`)
n'est pas retiré — le retirer demanderait de connaître les chaînes de caractères de chaque
langage. Faux positif résiduel documenté (§ 8), non corrigé.

### 3. Surfaces éligibles — resserrées

Aujourd'hui : `docs/` exclu, puis `.github/workflows/`, `scripts/`, `Makefile`, **et n'importe
quel fichier** `.sh` `.js` `.ts` `.py` `.yml` `.yaml` **n'importe où** dans l'arbre. C'est la
seconde source de faux positifs : `src/Billing.ts` avec une boucle métier et une variable
`budget`, un `docker-compose.yml`, un fichier de configuration. La liste devient une **liste
blanche de surfaces d'orchestration** ; tout ce qui n'y est pas est non éligible.

| Éligible | Motif de chemin | Pourquoi |
|---|---|---|
| CI GitHub | `.github/workflows/*.yml` ou `*.yaml` | déjà dans la spec d'origine |
| Action composite GitHub | `.github/actions/*/action.yml` ou `action.yaml` | **même rôle qu'un workflow** : c'est là qu'une équipe range l'étape de relance qu'elle réutilise ; l'exclure laissait un faux **négatif** (arbitrage du 2026-08-30, remarque Codex sur la PR #47). Cas de test TP7 |
| CI GitLab | `.gitlab-ci.yml` à la racine | nom **vérifié** le 2026-08-30 (doc GitLab CI/CD YAML) ; le mot-clé `retry:` / `max:` y existe |
| Make | `Makefile`, `makefile`, `GNUmakefile`, `*.mk`, à n'importe quelle profondeur | déjà dans la spec d'origine, étendu aux graphies équivalentes |
| Scripts d'orchestration | un segment de chemin `scripts/`, `bin/`, `tools/`, `hooks/` ou `.husky/` **et** une extension `.sh` `.bash` `.js` `.mjs` `.ts` `.py`, **ou** aucune extension avec une première ligne en `#!` | c'est là que vit un script de relance ; `hooks/` couvre `.claude/hooks/`, `.cursor/hooks/`, `.git/hooks/` sans nommer d'outil (« jamais la marque », spec 00 § 3) |
| Script racine | `*.sh` à la racine du `repo-context/` | forme courante (`run.sh`, `retry.sh`) |

| Non éligible | Exemple | Pourquoi |
|---|---|---|
| `docs/` | `docs/brainstorm/2026-06-auto-retry.md` (`arthur`, réel) | exclusion conservée telle quelle, en premier, avant toute autre règle |
| code applicatif | `src/Billing.ts`, `app/`, `lib/`, `tests/` | une boucle métier n'est pas un harnais |
| YAML hors CI | `docker-compose.yml`, `.claude/settings.yml`, et tout autre `.yml` de `.github/` (`dependabot.yml`, `ISSUE_TEMPLATE/*.yml`) | configuration, pas orchestration — la liste blanche ne prend de `.github/` que `workflows/` et `actions/*/action.yml` |
| tout `.md` | n'importe où | du texte n'exécute rien (§ 2) |

La liste blanche décrit des **rôles de fichiers**, jamais des marques : c'est la contrainte de
la spec 00 § 3 (« détecter `.claude/` serait une faute »). `.claude/hooks/retry.sh` est
éligible parce qu'il est sous `hooks/`, pas parce qu'il est sous `.claude/`.

### 4. La boucle comptée : relance et borne dans la même expression

`for i in $(seq 1 3)` est le vrai positif nommé par l'issue, et aucune borne séparée n'y
existe : le cap est **dans** l'expression. Mais comparer la seule valeur finale à 20 ne borne
rien (remarque Codex sur la PR #47) : `seq 100 -1 1` compte cent itérations et finit à 1,
`{-100..20}` en compte cent vingt et une et finit à 20. **La borne, c'est la longueur de la
suite, pas sa dernière valeur.**

La syntaxe acceptée est donc **restreinte à un début et un pas connus**, pour que la longueur
soit lisible sans exécuter quoi que ce soit :

| Forme acceptée | Longueur | Condition |
|---|---|---|
| `for … in $(seq N)` | N | `1 ≤ N ≤ LoopThresholds::COUNTED_MAX` |
| `for … in $(seq 1 N)` | N | début littéral `1`, `1 ≤ N ≤ LoopThresholds::COUNTED_MAX` |
| `for … in {1..N}` | N | début littéral `1`, `1 ≤ N ≤ LoopThresholds::COUNTED_MAX` |

`N` est un **entier littéral**, jamais une variable. Tout le reste n'est pas une borne
comptée : trois arguments (`seq 1 2 40`), un pas négatif (`seq 100 -1 1`), un début autre que
`1` (`seq 5 40`, `{-100..20}`), une variable (`seq 1 $MAX`, `seq 1 "${N}"`), un flottant
(`seq 1 0.5 3`). Ces formes ne sont pas rejetées comme boucles : elles retombent simplement
dans le cas commun — il leur faut une **borne séparée** dans la fenêtre (§ 1) pour compter.

`LoopThresholds::COUNTED_MAX = 20` est repris **tel quel** du § *Boucles* d'origine (« entier
≤ 20 associé ») — adaptation assumée, jamais sourcée ; il ne change pas de valeur ici, il est
seulement mis là où il s'applique. Au-delà de 20 itérations, ce n'est plus une relance sur
échec, c'est un traitement par lot.

Ce cap ne s'applique **qu'**à la boucle comptée. Une borne nommée reste une borne quelle que
soit sa valeur (`max_attempts: 50` est un cap explicite) : le comportement actuel des motifs
nommés est inchangé.

Motifs de borne, liste complète après ce chantier (les **deux premiers** sont ceux
d'aujourd'hui, inchangés ; les **trois derniers** sont ajoutés) :

| Motif | Exemple | Statut |
|---|---|---|
| cap nommé | `max_attempts`, `max-retries`, `max_retries`, `MAX_ITER`, `budget` | existant |
| comparaison sur `attempt(s)` | `attempts < 3`, `attempt <= 5` | existant |
| test numérique shell | `-ge 3`, `-gt 3`, `-le 3`, `-lt 3`, `-eq 3` | **ajouté** — forme `until [ $n -ge 3 ]`, sans quoi aucun Makefile réel n'est détectable |
| `max` avec valeur | `max: 2`, `max=2` | **ajouté** — forme `retry: max:` de GitLab CI, vérifiée le 2026-08-30 |
| borne comptée | `$(seq 3)`, `$(seq 1 3)`, `{1..3}` — début et pas connus, longueur ≤ 20 | **ajouté**, porte aussi la relance |

Les motifs de relance (`retry`, `until`, `while`, `rerun`, et la boucle comptée dans la seule
syntaxe restreinte ci-dessus) ne changent pas, et restent **disjoints** des motifs de borne :
un `MAX_ATTEMPTS = 3` seul ne relance rien et ne doit jamais satisfaire les deux (revue Codex
de la PR #19, conservée). Seule exception, explicite et bornée : la boucle comptée acceptée
porte les deux à la fois (§ 4), parce que le cap est **dans** l'expression qui relance.

### 5. Preuves rendues — mêmes claims, pointeur plus précis

L'`Evidence` de boucle garde son claim français et gagne les deux lignes et les deux tokens
qui l'ont déclenchée, pour que la preuve se revérifie sans relire tout le fichier :

- `repo-context/.github/workflows/ci.yml › loop = relance L9 « retry » + borne L12 « max_attempts »`
- `repo-context/Makefile › loop = relance L3 « until » + borne L5 « -ge 3 »`
- `repo-context/scripts/retry.sh › loop = relance et borne L4 « $(seq 1 3) »`

Les deux notes d'absence sont inchangées, texte et pointeur compris : « boucles : aucune
relance bornée trouvée » (`repo-context/ › bounded retry = none found`) et « boucles non
observables : repo-context/ absent ».

**Une note nouvelle, et une seule** (arbitrage du 2026-08-30, intégré ici au texte normatif) :
quand une relance est trouvée dans une surface éligible **sans borne dans la fenêtre**, l'axe
ne bouge pas d'un cran mais le fait est rendu —

- texte : « relance non bornée trouvée dans `<fichier>` » ;
- pointeur : `repo-context/<fichier> › unbounded retry = line N`, `N` étant la ligne de la
  relance ;
- **une seule occurrence** : le premier fichier concerné dans l'ordre de lecture, jamais une
  par ligne ni une par fichier — la sortie reste courte et déterministe ;
- elle **s'ajoute** à « boucles : aucune relance bornée trouvée », qui reste la note qui
  explique le plafond ; elle ne la remplace pas et **ne change aucun niveau** (le § *Boucles*
  d'origine le dit : une relance sans borne « n'est pas une boucle, c'est un risque »). Elle
  sert le geste « borner la relance » du § 06 ;
- **elle ne se déclenche que sur un token qui nomme une relance** — `retry`, `rerun`, `until`
  — jamais sur un `while` ou une boucle comptée seuls. La détection de boucle, elle, ne change
  pas : `while` + borne dans la fenêtre reste une boucle.

Cette dernière restriction vient d'une **observation sur donnée réelle**, la seule de ce
chantier : `profiles/self/repo-context/.claude/hooks/lib.js › ligne 41` contient
`while (i < tokens.length && tokens[i].startsWith('-'))` — une boucle de lecture d'arguments,
dans une surface éligible (`hooks/` + `.js`), sans aucune borne au sens du § 4. Sans cette
restriction, le dépôt se rendrait à lui-même une note « relance non bornée » pour une boucle
de parsing : un fait vrai, une lecture fausse. `while` est un mot du langage, `retry` est une
intention.

### 6. Cas dégradés

| Cas | Comportement |
|---|---|
| `repo-context/` absent | inchangé : Copper + note « boucles non observables » |
| `repo-context/` présent, aucune surface éligible | pas de boucle + note d'absence |
| fichier vide ou illisible (contenu `''`) | aucun match, aucune exception (le lecteur rend déjà `''`, spec 05) |
| contenu binaire | aucun match attendu ; si `preg_match` échoue (`false`), c'est **pas de match**, jamais une exception |
| relance et borne dans le même fichier, > 10 lignes d'écart | pas de boucle + note d'absence — c'est le cas de l'issue ; **+ note « relance non bornée trouvée dans `<fichier>` »** si le token nomme une relance (`retry`, `rerun`, `until`), jamais pour un `while` seul (§ 5) |
| relance sans aucune borne | pas de boucle (inchangé : « une relance sans borne est un risque ») + note pointée « relance non bornée » aux mêmes conditions (§ 5) |
| borne seule (`MAX_ATTEMPTS = 3`) | pas de boucle (inchangé), **aucune** note « relance non bornée » : rien ne relance |
| relance et borne uniquement en commentaire, de ligne **ou de bloc** | pas de boucle, **et aucune** note « relance non bornée » — un commentaire ne relance rien (§ 2) |
| boucle comptée hors syntaxe acceptée (`seq 100 -1 1`, `{-100..20}`, `seq 1 $MAX`) | pas de borne comptée ; boucle seulement s'il existe une borne séparée dans la fenêtre. Aucune note : une boucle comptée ne nomme pas une relance (§ 5) |
| plusieurs relances non bornées | une seule note, le premier fichier dans l'ordre de lecture |
| plusieurs paires valides | la première dans l'ordre de lecture ; une seule `Evidence` |
| fins de ligne `\r\n` | les lignes se coupent sur `\r?\n`, le numéro reste celui du fichier |
| fichier minifié d'une seule ligne portant les deux tokens | détecté (distance 0) : faux positif résiduel, hors surfaces éligibles en pratique (§ 8) |

### 7. Fixtures à créer

Deux étages, parce qu'ils ne prouvent pas la même chose. Une **fixture de profil**
(`fixtures/<nom>/` avec `README.md`, `profile.json`, `git-activity.json`, `repo-context/`)
prouve un **niveau rendu de bout en bout** ; une **fixture de détecteur** (contenu `RepoFile`
en ligne dans `tests/Domain/Axis/Harness/LoopDetectorTest.php`) prouve une **décision de
motif**. On ne crée une fixture de profil que là où le niveau global change.

**Fixtures de profil**

| Fixture | Contenu | Attendu |
|---|---|---|
| `fixtures/loop-far-apart/` (**à créer**) | mêmes signaux que `silver-loop` (Gold sur Taille et En parallèle, Silver sur Intervention, compteurs > 0 avec `agents_md = true`), mais `repo-context/scripts/deploy.sh` : `while` ligne 3, `budget` ligne 210, rien d'autre | Harness **Copper** + la seule note « boucles : aucune relance bornée trouvée » — **pas** de note « relance non bornée » : `while` ne nomme pas une relance (§ 5), et la fixture reste fidèle au cas de l'issue. Niveau global **Copper**, axe plafonnant **Harness seul**. C'est la fixture qui prouve que le resserrement change quelque chose de bout en bout (avant : Gold sur Harness, Silver global) |
| `fixtures/silver-loop/` (**existante, inchangée dans ses JSON**) | `repo-context/.github/workflows/ci.yml` : `retry` L9 / L10, `max_attempts: 3` L12 → écart 3 ≤ 10 | Harness **Gold**, niveau **Silver** plafonné par Intervention — verdict inchangé. Le `README.md` de la fixture est complété : la boucle tient **parce que** relance et borne sont à 3 lignes. Correction factuelle au passage : l'action est publiée sous `nick-fields/retry` depuis le 2022-02-15, `nick-invision/retry` est l'ancien chemin |

**Fixtures de détecteur — faux positifs (aucune boucle détectée)**

| # | Fichier | Contenu | Ce qui tranche |
|---|---|---|---|
| FP1 | `scripts/deploy.sh` | `while` L3, `budget` L210 | la fenêtre (§ 1) — cas exact de l'issue. **Aucune** note « relance non bornée » : `while` ne nomme pas une relance (§ 5) |
| FP1b | `scripts/flaky.sh` | `retry_deploy` L3, `budget` L210 | même écart, mais le token **nomme** une relance → aucune boucle **et** note « relance non bornée trouvée dans `scripts/flaky.sh` », pointeur `… › unbounded retry = line 3` |
| FP2a | `scripts/ci.sh` | une seule ligne : `# retry the test, max_attempts=3` | le commentaire de ligne (§ 2) |
| FP2b | `tools/runner.js` | bloc `/*` L4, `retry the build` L5, `max_attempts: 3` L6, `*/` L7, du code sans motif ensuite | le **corps** d'un commentaire de bloc : aucune de ces lignes ne commence par `*`, seul l'état de bloc les exclut (§ 2) |
| FP3 | `scripts/config.js` | `export const MAX_ATTEMPTS = 3;` | borne sans relance (existant, conservé) ; aucune note « relance non bornée » |
| FP4 | `src/Billing.ts` | `while (…)` L10 et `budget` L12 — **à 2 lignes** | la surface (§ 3) : la fenêtre passerait, le chemin non — surface non éligible, donc **aucune** note non plus |
| FP5 | `docs/brainstorm/2026-06-auto-retry.md` (réel, `arthur`) | « Not decided » | `docs/` exclu (existant, conservé) |
| FP6 | `scripts/seed.sh` | `for i in $(seq 1 50); do seed_user $i; done` | longueur 50 > 20 : lot, pas relance bornée (§ 4). Aucune note : une boucle comptée ne nomme pas une relance (§ 5) |
| FP7 | `scripts/rollback.sh` | `for i in $(seq 100 -1 1); do …` puis, ligne suivante, `{-100..20}` | pas de début/pas connus : la valeur finale (1, puis 20) est ≤ 20 mais la **longueur** vaut 100 puis 121 — la syntaxe restreinte du § 4 les refuse toutes deux. Aucune note (§ 5) |

**Fixtures de détecteur — vrais positifs (boucle détectée, preuve pointée)**

| # | Fichier | Contenu | Paire |
|---|---|---|---|
| TP1 | `Makefile` | `retry:` / `@n=0; \` / `until make test; do \` / `n=$$((n+1)); \` / `[ $$n -ge 3 ] && exit 1; \` / `done` | `until` L3 ↔ `-ge 3` L5 (écart 2) — remplace la fixture actuelle dont la borne était en commentaire |
| TP2 | `.github/workflows/ci.yml` | celui de `silver-loop` | `retry` L9 ↔ `max_attempts` L12 (écart 3) |
| TP3 | `scripts/retry.sh` | `for i in $(seq 1 3); do make test && break; done` | même ligne (écart 0), borne comptée |
| TP4 | `scripts/loop.sh` | `MAX_ATTEMPTS=3` L2, puis `until make test; do` L6 | borne **avant** relance (écart 4) |
| TP5 | `.gitlab-ci.yml` | `test:` / `script: make test` / `retry:` / `  max: 2` | `retry` ↔ `max: 2` (écart 1) |
| TP6 | `.claude/hooks/verify.sh` | `until make test; do n=$((n+1)); [ $n -ge 3 ] && break; done` | surface `hooks/`, pas la marque |
| TP7 | `.github/actions/test-with-retry/action.yml` | `runs:` / `using: composite` / `steps:` / `- uses: nick-fields/retry@v3` / `with:` / `max_attempts: 3` | action composite (§ 3) : `retry` ↔ `max_attempts` à 2 lignes |

**Bordures des constantes**, chacune figée par un test nommé :

- proximité : relance L1 / borne L11 (écart 10) → détectée ; relance L1 / borne L12 (écart 11)
  → non détectée — changer `LoopThresholds::PROXIMITY_LINES` casse un test ;
- borne comptée : `$(seq 1 20)` → détectée ; `$(seq 1 21)` → non détectée — changer
  `LoopThresholds::COUNTED_MAX` casse un test.

### 8. Ce qui reste non vérifié

- **Aucun des quatre profils fournis n'a de boucle** (`docs/calibration.md`). Motifs, fenêtre,
  liste blanche et borne comptée ne sont éprouvés que sur des fixtures maison : le chantier
  réduit une classe de faux positifs, il ne prouve aucun vrai positif sur donnée réelle.
- **Le proxy de fond.** La grille dit « un script relance **l'IA** tant qu'une commande du
  projet échoue ». La détection ne sait pas si la commande relancée invoque un modèle : elle
  constate une relance bornée d'une commande. Un `until make test` piloté par un humain est
  indistinguable d'un `until claude -p …`. Proxy assumé, non vérifié, inchangé depuis la spec
  d'origine.
- **`LoopThresholds::PROXIMITY_LINES = 10`** : marge sur trois formes vérifiées, pas mesure
  sur un corpus.
- **`LoopThresholds::COUNTED_MAX = 20`** : valeur déjà écrite au § *Boucles* d'origine, sans
  source. La **restriction de syntaxe** qui la rend effective (§ 4 : début et pas connus)
  est un choix de ce chantier, non vérifié sur donnée réelle.
- **La note « relance non bornée »** est une sortie nouvelle. Son volume n'est mesuré que sur
  les trois `repo-context/` réels disponibles : `leodagan` et `arthur` n'ont **aucun** fichier
  éligible portant un motif de relance (leurs occurrences de `retry`/`while` sont toutes dans
  des `.md`, vérifié le 2026-08-30) ; `profiles/self` en a **une**, la boucle de parsing de
  `.claude/hooks/lib.js › 41`, écartée par la restriction aux tokens qui nomment une relance
  (§ 5). Trois dépôts ne sont pas une mesure : le volume sur un `scripts/` fourni reste
  inconnu, et la note est plafonnée à une occurrence pour que ce risque reste borné.
- **`.gitlab-ci.yml`** : nom et existence du mot-clé `retry` vérifiés à la doc GitLab le
  2026-08-30 ; la forme exacte `retry: max: <n>` et les valeurs acceptées **n'ont pas** pu
  l'être (la page rendue ne détaille pas `max`). TP5 est donc une fixture **plausible, non
  vérifiée**. Aucun profil fourni n'a de CI GitLab.
- **CircleCI** (`.circleci/config.yml`) : documentation inaccessible le 2026-08-30 (404) →
  **non vérifié**, donc **hors** liste blanche pour l'instant.
- **Faux positifs résiduels non mesurés** : commentaire en fin de ligne de code, fichier
  minifié d'une seule ligne, boucle comptée qui ne relance rien mais tient dans une surface
  d'orchestration.
- **Le taux de faux négatifs est inconnu** : une relance bornée écrite dans une forme absente
  de la liste (Python `for attempt in range(3)`, `tenacity`, `retry` npm, Jenkinsfile
  `retry(3)`) n'est pas détectée. Non traité ici : rien ne permet de choisir entre ces formes
  sans donnée.

### Tests

FP1 `while` L3 + `budget` L210 dans `scripts/deploy.sh` → aucune boucle et **aucune** note
(cas exact de l'issue) · FP1b `retry_deploy` L3 + `budget` L210 dans `scripts/flaky.sh` →
aucune boucle, **et** note « relance non bornée trouvée dans `scripts/flaky.sh` » pointée
`unbounded retry = line 3` · FP2a ligne
unique de commentaire `# retry … max_attempts=3` → aucune boucle, aucune note · FP2b corps
d'un `/* … */` multi-lignes portant `retry` L5 et `max_attempts` L6 (aucune ligne ne commence
par `*`) → aucune boucle, aucune note ; même fichier avec le bloc fermé avant les deux motifs
→ boucle détectée (l'état de bloc s'ouvre **et** se referme) · FP3 `MAX_ATTEMPTS = 3` seul →
aucune boucle, aucune note (existant, conservé) · FP4 `src/Billing.ts` avec les deux motifs à
2 lignes → aucune boucle, aucune note, surface non éligible · FP5
`docs/brainstorm/2026-06-auto-retry.md` d'`arthur` → aucune boucle (existant, conservé) · FP6
`$(seq 1 50)` → aucune boucle, aucune note · FP7 `$(seq 100 -1 1)` et `{-100..20}` → aucune
boucle et aucune note : la valeur finale (1, puis 20) est ≤ 20 mais la **longueur** vaut 100
puis 121 — seule la syntaxe restreinte du § 4 (début `1`, pas connu, `N` littéral) est une
borne comptée · TP1
`Makefile` `until` + `-ge 3` → boucle, pointeur citant L3 et L5 · TP2 workflow `retry` +
`max_attempts` → boucle, pointeur citant L9 et L12 · TP3 `for i in $(seq 1 3)` → boucle, même
ligne · TP4 borne déclarée avant la relance → boucle · TP5 `.gitlab-ci.yml` `retry:` /
`max: 2` → boucle · TP6 `hooks/verify.sh` → boucle (surface, jamais la marque) · TP7
`.github/actions/test-with-retry/action.yml` → boucle (action composite éligible) · écart
exactement 10 → boucle, écart 11 → aucune boucle (fige `LoopThresholds::PROXIMITY_LINES`) ·
`$(seq 1 20)` → boucle, `$(seq 1 21)` → aucune boucle (fige `LoopThresholds::COUNTED_MAX`) ·
deux paires valides dans le même `repo-context/` → une seule `Evidence`, la première dans
l'ordre de lecture · deux relances non bornées dans deux fichiers → une seule note, le premier
fichier dans l'ordre de lecture · fichier vide et contenu binaire → aucun match, aucune
exception · fixture de profil `loop-far-apart` → Harness Copper, niveau global Copper plafonné
par Harness seul, **une seule** note : « boucles : aucune relance bornée trouvée » (le `while`
de la fixture ne déclenche pas la note « relance non bornée », § 5) · fixture de profil
`silver-loop` → Harness Gold, niveau Silver
plafonné par Intervention (verdict inchangé, `docs/calibration.md` toujours vrai) ·
`no-repo-context` → Copper + note « boucles non observables » (inchangé) · `leodagan` et
`arthur` (réels) → Copper, aucune note « relance non bornée » (aucun fichier éligible portant
un motif chez eux) · `profiles/self` (réel) → **aucune** note « relance non bornée » malgré le
`while` de `.claude/hooks/lib.js › 41` : c'est le test qui fige la restriction aux tokens qui
nomment une relance (§ 5) · un `while` non borné dans une surface éligible → aucune note ; le
même fichier avec `retry` à la place → note.

### Arbitrages du 2026-08-30 — historique

Ces sept arbitrages (validation de la spec par Jonathan, puis remarques Codex sur la PR #47)
sont **intégrés au texte normatif ci-dessus** : cette liste ne décide plus rien, elle dit
seulement où chaque décision a atterri. En cas d'écart, **le texte des § 1 à 8 fait foi**.

| Arbitrage | Intégré au |
|---|---|
| 1. Relance non bornée → note pointée, jamais un niveau | § 5 (texte et pointeur), § 6, § *Tests* |
| 2. `for … in $(seq …)` = relance + borne, aucun `break` exigé | § 4, avec la syntaxe restreinte qui rend la borne effective |
| 3. Le motif `budget` reste (il vient du brief) | § 4, table des motifs de borne |
| 4. `.github/actions/*/action.yml` éligible | § 3, liste blanche ; cas TP7 |
| 5. Constantes dans `LoopThresholds`, à côté de `LoopPatterns` | § 1 (nommage et justification), § 4, § 7, § 8 |
| 6. Une seule fixture de profil (`loop-far-apart`) plus les cas unitaires | § 7 |
| 7. `silver-loop` : `nick-invision/retry@v2` → `nick-fields/retry@v3` | § 7, ligne `silver-loop` |
