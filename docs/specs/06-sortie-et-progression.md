# 06 — Sortie et progression

Sortie minimale imposée par le sujet : « un niveau · ce qui a mené là · comment progresser ».
Texte seul (tranché le 2026-08-26), pas de `--json`.

> **Amendé le 2026-08-31 — chantier « Sortie lisible », issue #65.** La visite guidée (spec 09
> § 5) a posé onze écarts de forme aux étapes 1 et 2 : 1.1 à 1.10 et 2.3. Ils disent tous la même
> chose — la sortie était lisible par qui connaît le dépôt, pas par la personne évaluée. Ce
> chantier refond **la forme rendue** et rien d'autre : le niveau reste le minimum des quatre
> axes, la médiane décide toujours, un ex æquo n'est jamais moyenné, chaque affirmation porte
> toujours son pointeur vérifiable (règle 3 d'`AGENTS.md`) — le pointeur cesse seulement d'être
> *toute* la ligne. Aucun seuil de domaine, aucun verdict, **aucun des trois statuts** ne change,
> pas même son libellé (`AGENTS.md`, règle 6 : décision figée, § 6). Tout ce qui porte ici un nom
> de classe, de constante, de champ ou de pointeur a été relevé dans le dépôt le 2026-08-31 ; ce
> qui n'a pas pu l'être est marqué **non vérifié**.

## 1. Cinq règles (validées le 2026-08-25 ; règles 2, 3 et 4 reformulées le 2026-08-31)

1. **L'explication sort du calcul.** Le niveau est le minimum des axes ; l'axe qui plafonne est
   du même coup la cause et le chemin. Rien n'est reconstruit après coup.
2. **« Pourquoi P plutôt que Q »**, avec Q = le niveau immédiatement supérieur (Miller, 2019 :
   sélective, contrastive). Structure contrainte : **niveau atteint, niveau suivant et sa
   condition de passage, l'axe — ou les axes — qui restent à monter.**
3. **Un seul axe est détaillé fait par fait : celui qui bloque.** Les autres sont rendus quand
   même, chacun avec le niveau qu'il atteint et son pointeur, sans détail (§ 5.4). Quand
   plusieurs axes bloquent, **chacun est nommé**, dans l'ordre d'actionnabilité, et la sortie dit
   que **chacun devra monter** : rien n'est moyenné, aucun n'est tu (§ 5.2).
4. **Chaque affirmation cite où le fait a été observé** : fichier › champ = valeur. Une
   affirmation sans pointeur est un défaut de l'outil (`Evidence` sans pointeur ne se construit
   pas). **La phrase passe d'abord, le pointeur vient en appui** (§ 2).
5. **Dire le geste, pas la case manquante** (Karimi et al., recourse). Ordre par
   actionnabilité causale, jamais par colonne de grille :

| Axe | Actionnabilité | Forme |
|---|---|---|
| Harness | actionnable | geste précis et vérifiable, **toujours en premier** |
| En parallèle | actionnable, conditionné par Harness | en second |
| Intervention | mutable, non actionnable | nommer ce qu'il faut cadrer/automatiser en amont |
| Taille | mutable, non actionnable | dire que la taille suit le dispositif ; jamais « fais des PR plus grosses » |

## 2. La grammaire d'une ligne — la phrase d'abord, le pointeur en appui

C'est la grammaire que tous les écarts de forme supposent ; elle vaut partout dans la sortie.

**Une `Evidence` rendue = deux lignes logiques**, dans cet ordre et jamais l'inverse :

```
    <une phrase en français, qui dit le fait et son échelle (§ 3)>
      <fichier> › <champ> = <valeur>
```

Cinq invariants, chacun testable (§ 12) :

1. **aucune ligne de pointeur sans sa phrase** — c'est ce que la forme antérieure violait : seule
   la première `Evidence` d'un axe portait une phrase, les suivantes ne rendaient que leur
   pointeur. **Toutes les `Evidence` d'un axe sont rendues, chacune avec sa phrase** ;
2. **aucune phrase sans son pointeur** (règle 4) ;
3. **le pointeur n'est jamais replié, jamais tronqué.** `›` et la valeur ne peuvent pas être
   séparés par un retour à la ligne, et une valeur longue n'est pas coupée : un pointeur sert à
   revérifier le fait, un pointeur amputé ne sert plus à rien. **La ligne de pointeur est donc la
   seule ligne de la sortie autorisée à dépasser `MAX_WIDTH`** — cas réels : une note de profil
   recopiée en valeur, un chemin imbriqué sous `repo-context/`. Aucune continuation, aucune
   ellipse : la ligne est longue et elle se copie-colle telle quelle ;
4. **la phrase est produite en amont du rendu, jamais par lui** : elle est le `claim` d'une
   `Evidence`. Deux producteurs, et **deux seulement**, vérifiés le 2026-08-31 — les quatre
   évaluateurs d'axe (`src/Domain/Axis/`), et `EvaluateProfileHandler::whiteVerdicts()`
   (`src/Application/`), qui fabrique les deux `Evidence` du filtre White sans passer par un
   évaluateur (spec 05 § *Filtre White*). Les deux passent au format phrase + échelle. Le
   `TextRenderer` n'écrit jamais un seuil, ni un nom de bande, ni une valeur ;
5. **un fait déjà rendu ne se redit pas** : deux `Evidence` de même pointeur et de même phrase ne
   sortent qu'une fois, la première. Cas réel, et c'est pourquoi la règle est ici et pas seulement
   au § 5.6 : `whiteVerdicts()` partage les **mêmes** deux `Evidence` entre les quatre axes — sans
   cette règle, un profil White imprimerait quatre fois les deux mêmes lignes. L'entrée d'axe
   garde sa ligne de tête, elle ne répète pas la preuve.

Les specs 01 à 04 continuent de décider **quelles** `Evidence` existent et **quel pointeur**
chacune porte (leurs § *Preuves rendues*) ; **la forme de la phrase qui les accompagne se décide
ici**, et nulle part ailleurs.

Repli de ligne : `MAX_WIDTH = 100` colonnes, constante du rendu, **adaptation assumée** (largeur
de terminal courante) — inchangée par ce chantier, sauf pour l'exception de l'invariant 3.

## 3. Les chiffres et leur échelle — aucun chiffre nu

Écart 1.9 : `median_files_changed = 29` ne dit à personne si 29 est beaucoup. **Toute valeur
chiffrée rendue est accompagnée de l'échelle qui la qualifie**, dans la phrase, jamais dans le
pointeur :

| Ce qui est rendu | Ce que l'échelle nomme | D'où elle vient |
|---|---|---|
| la valeur mesurée | la bande ou la cellule atteinte, et le seuil qui la borne | la constante nommée que l'évaluateur a lue |
| le cran suivant | le seuil qu'il faudrait franchir pour changer de bande | la constante nommée du cran au-dessus |

**Deux seuils au maximum par chiffre** : celui de la bande atteinte, celui du cran suivant. Une
échelle plus longue redevient un tableau de seuils, et le tableau de seuils est la spec de l'axe,
pas la sortie.

**Un chiffre sans seuil dans l'outil le dit.** Deux cas, tous deux nommés :

- les mesures Sonar — l'outil n'a aucun seuil dessus (spec 05 § *Sonar*) : la phrase porte
  « citée sans jugement », et aucune bande n'est inventée ;
- les valeurs qui corroborent sans décider (`merged_without_human_edit_after_open`,
  `max_concurrent_branches`) : la phrase dit **pourquoi** elles ne décident pas — la médiane
  décide, jamais le maximum (règle 5 d'`AGENTS.md`).

Cas dégradé : un axe dont la cellule ne se chiffre pas (Harness — « ce qui est en place », pas un
volume) rend l'échelle en toutes lettres — ce qui est présent, ce que la cellule suivante exige —
sans jamais fabriquer un nombre pour faire nombre.

## 4. Les pièces nommées à leur première citation

Écart 1.3 : `git-activity.json` n'est expliqué nulle part dans la sortie, alors que la personne
évaluée ne l'a peut-être jamais ouvert.

**À la première citation d'une pièce dans la sortie d'un profil, et une seule fois**, une
**légende** d'une phrase précède le premier pointeur qui la cite, au même niveau d'indentation.
La légende dit ce qu'est la pièce, jamais ce qu'elle vaut.

Les légendes vivent dans une table nommée du domaine, `SourceGlossary`, à côté de la table des
gestes : c'est du texte destiné à l'utilisateur, donc en français (spec 00 § 4), et il se décide,
il ne se rédige pas au rendu.

| Pièce | Légende rendue |
|---|---|
| `git-activity.json` | l'activité git du profil, déjà agrégée : PR, commits, branches et fichiers de contexte, sur la période du fichier |
| `repo-context/` | la copie des fichiers de configuration IA trouvés à la racine du dépôt |
| `profile.json` | l'identité du profil et la liste des pièces annoncées |
| `sonar-measures.json` | les mesures de qualité fournies avec le profil, citées sans jugement |

**La légende n'est pas une affirmation sur le profil** : elle ne cite aucune valeur mesurée, et le
nom de la pièce qu'elle porte est sa propre référence. La règle 4 (une affirmation, un pointeur)
ne s'y applique donc pas, et le contrôle du § 12 l'exclut nommément — c'est écrit ici pour qu'on
ne l'y remette pas par erreur.

Cas dégradé : une pièce absente de la table ne reçoit **aucune** légende — le rendu n'en invente
jamais une. C'est un manque de la table, il se corrige dans la table.

## 5. Format de sortie

Icône **et** nom du niveau, jamais la couleur seule ni l'icône seule (lecteur daltonien parmi les
lecteurs, et la vidéo est muette). Quatre choses, dans cet ordre fixe : l'**en-tête** (§ 5.1), le **tableau de synthèse** des quatre axes (§ 5.4), **déjà acquis**
(§ 5.4), **ce qui limite le niveau** — chaque axe limitant détaillé (§ 5.2) —, **comment monter
d'un cran** (§ 5.5), les **notes**, qui ne sortent plus que sur un profil non évaluable (§ 5.6). Ordre narratif arbitré le 2026-08-31 : l'état, les
forces, puis ce qui limite, puis les gestes — jamais un manque avant les acquis.

### 5.1 L'en-tête — quatre lignes logiques

Ordre arbitré par Jonathan le 2026-08-31 : le lecteur rencontre la personne avant le verdict.

```
arthur — développeur indépendant
Niveau AIDD : 🥉 Copper
Échelle des niveaux : ❖ White  🔺 Red  🔹 Blue  🟢 Green  [🥉 Copper]  🥈 Silver  🥇 Gold
Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.
```

- **ligne 1** — l'identité seule : `profile_id — role`, tels que `profile.json` les donne ;
- **ligne 2** — le niveau : `Niveau AIDD : …`, `Level::label()` fournissant « icône + nom » ;
- **ligne 3 — l'échelle nommée (écart 1.2)**, préfixée `Échelle des niveaux : ` : les sept niveaux, **chacun avec son nom à côté de son
  icône**, le niveau atteint entre crochets. Une frise d'icônes nues ne se lit pas sans la grille
  sous les yeux, et ne se lit pas du tout si le terminal ne rend pas les emoji ;
- **ligne 4 — la fiabilité (écart 1.7)** : § 6 ;
La ligne **« Niveau suivant » sort juste après le tableau du § 5.4** (ordre narratif du
2026-08-31, écarts 1.1 et 1.5) : le mot « visé » disparaît, la ligne nomme le niveau suivant
(icône + nom) **et sa condition de passage** — les axes qui doivent y monter.

**En fourchette** (statut `évalué, confiance basse`), l'en-tête garde ses quatre lignes : la
ligne 2 dit `Niveau AIDD : entre 🔹 Blue et 🥉 Copper — …`, la frise ouvre le crochet sur le
plancher et le ferme sur le plafond (convention existante, conservée), la ligne 3 dit ce qui
manque (§ 6.1) et la ligne 4 vise le cran au-dessus du **plancher**.

**La condition de passage, exactement** :

| Cas | Ligne rendue |
|---|---|
| un seul axe bloque | `Niveau suivant : 🟢 Green — il faut que Harness y monte.` |
| plusieurs axes bloquent | le niveau suivant, **les axes nommés un par un**, puis la phrase qui dit pourquoi ils sont plusieurs : chacun doit monter — c'est l'axe le plus bas qui donne le niveau attribué |
| le profil est déjà Gold | `Niveau suivant : aucun — 🥇 Gold est le dernier niveau de la grille.` |
| le cran suivant est Gold et c'est Intervention qui bloque | le niveau suivant est nommé **hors d'atteinte ici** : l'axe Intervention plafonne à 🥈 Silver, « cadrage compris » ne se constate dans aucune pièce fournie (spec 03) |

La phrase sur la règle du minimum ne sort **que** lorsque plusieurs axes bloquent : c'est là
qu'elle explique quelque chose. C'est ce qui remplace « axe bloquant … (ex æquo) » — deux termes
que la sortie n'emploie plus (§ 10), sans rien perdre de l'invariant : les axes ex æquo sont tous
nommés, dans l'ordre du § 5.3, et aucune moyenne n'est jamais rendue.

### 5.2 *Ce qui limite le niveau* — l'axe qui limite, détaillé

Le tableau du § 5.4 est rendu plus haut, sous l'en-tête ; ce bloc porte une entrée par axe
limitant, dans l'ordre du § 5.3. Chaque entrée porte, sur sa ligne de tête, le nom de l'axe, le niveau qu'il
atteint (icône + nom) et le fait qu'il bloque ; puis ses `Evidence` à la grammaire du § 2.

```
Ce qui limite le niveau

  Harness — 🥉 Copper : l'un des deux axes qui bloquent
    Un fichier mémoire est versionné à la racine du dépôt.
      git-activity.json — l'activité git du profil, déjà agrégée : PR, commits, branches et
        fichiers de contexte, sur la période du fichier.
      git-activity.json › context_files.agents_md = true
    Six fichiers de contexte versionnés : au moins un compteur non nul, c'est le « behavior »
    que Green et Copper demandent.
      git-activity.json › context_files = {rules:0, skills:4, hooks:0, agents:2}
    Aucune relance automatique bornée n'a été trouvée : c'est ce que Silver demande en plus.
      repo-context/ › bounded retry = none found
```

Les pointeurs de cet exemple sont ceux que le domaine produit réellement (vérifié le 2026-08-31
dans `HarnessEvaluator` et à la spec 02 § *Preuves rendues*) ; les phrases, elles, sont la
**forme** attendue, pas leur texte figé : le texte est celui que le producteur du `claim` écrit
(§ 2, invariant 4).

### 5.3 L'ordre des axes

Un seul ordre dans toute la sortie, celui de l'actionnabilité causale (règle 5) : **Harness, En
parallèle, Intervention, Taille**. Il est déjà porté par une seule constante,
`RecommendationPolicy::AXIS_ORDER` ; le rendu la lit, il n'en garde pas de copie.

### 5.4 L'état des quatre axes — synthèse, puis les autres axes

Écart 1.6 : la sortie ne montrait que ce qui bloque, et la personne évaluée ne voyait jamais ce
qu'elle avait déjà acquis. **Les quatre axes sont désormais rendus, sans exception**, en deux
temps — tranché le 2026-08-31 : un tableau de synthèse aligné, puis les autres axes en clair. Le tableau est rendu par le
composant Table de symfony/console sur un BufferedOutput — largeurs fixées dans le code, aucune
dépendance au terminal (arbitré par Jonathan le 2026-08-31). Il ne porte aucun pointeur : seule
sa colonne « Constat » se replie (constante SYNTHESIS_CLAIM_MAX_WIDTH, bornée pour que la rangée
la plus large reste sous MAX_WIDTH), rien de vérifiable n'y est jamais coupé.

**Le tableau de synthèse**, rendu juste sous l'en-tête, avant *Déjà acquis* —
colonnes Axe / Niveau / Constat, une rangée par axe, le « Constat » étant la phrase de la
première `Evidence` de l'axe (celle qui décide) :

```
  +--------------+--------------------+----------------------------------------------------+
  | Axe          | Niveau             | Constat                                            |
  +--------------+--------------------+----------------------------------------------------+
  | Harness      | 🥉 Copper ← niveau global | context engineering acquis : un fichier mémoire …  |
  | En parallèle | 🥇 Gold            | 4 chantiers de front en médiane, habituellement …  |
  +--------------+--------------------+----------------------------------------------------+
```

- **les quatre axes**, dans l'ordre du § 5.3 — Harness, En parallèle, Intervention, Taille —,
  une rangée chacun ;
- chacun : nom de l'axe, niveau atteint **icône et nom**, et une mention quand il y a lieu —
  `← niveau global` sur la rangée (ou les rangées ex æquo) dont le niveau est le niveau final
  (« bloque » était du jargon, remplacé le 2026-08-31), `(incertain)` pour un axe non tranché.
  **Jamais la couleur ni l'icône seule** pour dire lequel limite ;
- un axe en fourchette y montre **plancher et plafond** : `entre 🔹 Blue et 🥉 Copper
  (incertain)` ;
- **c'est une exception nommée à la règle 4** : la synthèse **récapitule des lignes pointées
  rendues juste en dessous**, elle n'affirme rien de neuf. Elle est exclue du contrôle du § 12 au
  même titre que la légende du § 4. Avec la légende et la pièce nommée par son chemin en `non
  évaluable` (§ 6.2), **cela fait trois exceptions, et le fichier n'en connaît pas d'autre** ;
- au-delà de `MAX_WIDTH` elle se replie comme n'importe quelle phrase (§ 2), **sur un séparateur
  ` · `**, jamais au milieu d'un couple axe–niveau.

**Les autres axes**, après le détail de l'axe (ou des axes) qui bloquent, sous leur propre
intertitre `Déjà acquis pour <niveau suivant>`. Une entrée par axe qui ne bloque pas, portant :

1. le **nom de l'axe** ;
2. le **niveau qu'il atteint**, icône **et** nom ;
3. **une phrase** qui dit ce qu'on observe, avec son échelle (§ 3) ;
4. **un pointeur**, à la grammaire du § 2.

Trois règles s'y ajoutent, et elles suffisent à rendre le bloc déterministe :

- **ordre** : celui du § 5.3 ;
- **pas de détail** : un axe qui ne bloque pas rend l'`Evidence` qui a décidé son niveau, et elle
  seule — le détail fait par fait reste réservé à l'axe qui bloque (règle 3) ;
- **aucun axe omis** : un axe sans verdict est impossible hors `non évaluable` ; s'il survenait,
  il se rendrait comme non observable (§ 6), jamais en silence.

**Un axe en fourchette qui ne bloque pas garde tout ce qui le concerne** — décidé le 2026-08-31,
et c'est ce qui remplace le bloc *Incertitude sur les autres axes* qui existait à part. Cas réel :
sur `fixtures/short-sample`, En parallèle plafonne, mais Intervention et Taille sont en fourchette
elles aussi ; leur doute ne doit pas disparaître de la sortie parce qu'elles ne bloquent pas. Son
entrée porte donc, en plus des quatre points ci-dessus :

- le niveau rendu est le **plancher** de la fourchette — c'est lui qui est acquis, le reste n'est
  pas prouvé, et c'est ce qui rend l'intertitre vrai ;
- la mention `(fourchette)` sur la ligne de tête, et une ligne `fourchette : entre <plancher> et
  <plafond>` ;
- une ligne `pour trancher : <manque chiffré>` — `N PR de plus` pour un échantillon court,
  `fournir le champ <x>` pour un signal absent (§ 6.1). **C'est le seul geste qu'un axe non
  bloquant reçoit** : il ne prend pas de geste de la table du § 7, qui ne s'adresse qu'à ce qui
  bloque (règle 5), mais son doute reste levable et la sortie dit comment.

### 5.5 *Comment monter d'un cran* — un seul bloc (écart 1.8)

« Comment monter d'un cran » et « Prochaine quête » disaient deux fois la même chose, le second
recopiant le premier geste. **Un seul bloc**, où la quête est le premier élément du plan :

```
Comment monter d'un cran — vers 🥈 Silver
  1. Harness (à faire en premier) — ajouter une relance automatique bornée (N essais visibles)
     dans la CI ou un script, sur une commande du projet.
     Ce qui le prouvera : repo-context/ › bounded retry
     Aujourd'hui : repo-context/ › bounded retry = none found
  2. Intervention — automatiser la validation (tests, lint, duplication) pour qu'aucune reprise
     humaine ne soit nécessaire après ouverture.
     Ce qui le prouvera : pull_requests.median_correction_commits_after_open
```

- un geste par axe bloquant, numéroté, dans l'ordre du § 5.3 ;
- **chaque** geste porte le champ qui devra bouger (`Recommendation::$proofField`, § 7) ;
- **le premier seul** porte la mention « à faire en premier » et la ligne « Aujourd'hui : », le
  pointeur de l'état d'où il part. C'est la « prochaine quête » de la gamification (§ 8), qui
  cesse d'être un bloc pour devenir la tête du plan ;
- un axe bloquant **en fourchette** garde sa ligne `pour trancher : <manque chiffré>` sous son
  geste, comme aujourd'hui.

### 5.6 *Notes* — retirées de la sortie évaluée (2026-08-31)

Arbitré par Jonathan le 2026-08-31, en remplacement de la déduplication du premier amendement :
sur un profil `évalué` ou `évalué, confiance basse`, **le bloc Notes ne sort plus** — pics
écartés, corroborations et mesures Sonar n'apprennent rien à la personne évaluée, et tout fait
qui décide vit déjà dans les blocs pointés. Les notes restent portées par `Assessment::$notes`
(rien n'est perdu pour un autre rendu) ; sur `non évaluable`, le bloc reste rendu : il dit ce
qui a pu être lu malgré tout (§ 6.2). Le test du § 12 vérifie l'absence du bloc sur un profil
évalué, et sa présence sur `non évaluable`.

## 6. Fiabilité de l'évaluation — Raccord avec les statuts

Écart 1.7 : « statut » ne dit pas ce qu'il qualifie. Ce que le mot qualifie, c'est **la fiabilité
de l'évaluation** — et c'est ce mot qui introduit la ligne. **Les trois statuts gardent leur nom,
y compris à l'écran** : `évalué`, `évalué, confiance basse`, `non évaluable` sont une décision
figée (`AGENTS.md`, règle 6 et *Décisions figées*), ils ne se rebaptisent pas. Ce chantier ne
change qu'une chose : le libellé canonique **cesse d'être seul** et gagne, sur la même ligne, ce
qu'il veut dire. Il reste donc `grep`-able dans la sortie, mot pour mot.

| Statut | Ce que la sortie affiche |
|---|---|
| `évalué` | `Fiabilité : évalué — les quatre axes ont assez de matière pour être tranchés.` |
| `évalué, confiance basse` | `Fiabilité : évalué, confiance basse — <les axes en fourchette, nommés> ; <le manque, chiffré> ; le niveau est donné en fourchette.` |
| `non évaluable` | pas de ligne *Fiabilité* : le libellé est porté par l'en-tête lui-même (§ 6.2) |

La ligne *Fiabilité* nomme **tous** les axes en fourchette, pas seulement celui qui plafonne :
c'est l'autre moitié de la décision du § 5.4, et les deux se tiennent — la ligne les annonce, les
entrées d'axe les détaillent.

### 6.1 Les cas dégradés, en langage clair (écart 2.3)

Tout cas dégradé rend **trois choses, dans cet ordre**, par axe concerné :

1. **ce qui manque**, nommé, avec son pointeur ;
2. **ce que ce manque empêche** — jamais « fourchette » tout seul : ce qu'on ne peut pas trancher
   et jusqu'où ;
3. **le geste qui le lèverait** — fournir le champ, ou attendre N PR de plus.

| Cas | Ce que la sortie dit |
|---|---|
| échantillon court sur un axe (spec 05 § *Planchers*) | le manque en PR **et** le plancher qui le définit ; entre quels deux niveaux on ne peut pas trancher, et que le dossier ne permet pas de choisir |
| signal absent — le champ décideur est `null` (spec 05 § *Signal absent*) | l'axe est **non observable**, le champ est nommé avec son pointeur `= absent`, et la phrase dit que sans lui l'axe ne se tranche pas au-dessus de son plancher ; le geste est **« fournir le champ »**, adressé à qui constitue le dossier |
| médiane à `0` | une valeur, jamais une absence : elle se cite comme telle (spec 05) |

La fourchette reste rendue — plancher et plafond, icône et nom — mais **jamais seule** : la phrase
qui l'accompagne dit ce qui empêche de trancher. Le mot « manque N PR » ne sort que lorsque le
manque **est** un nombre de PR ; un champ absent ne se rend jamais comme un échantillon court.
Ceci vaut pour tout axe en fourchette, qu'il bloque (§ 5.5) ou non (§ 5.4).

### 6.2 `non évaluable`

L'en-tête des quatre lignes est remplacé par :

```
⛔ Non évaluable — galahad (stagiaire)
Ce qui manque : git-activity.json, absent ou illisible.
Ce que ça empêche : les quatre axes se calculent depuis ce fichier ; sans lui, aucun niveau ne
                    peut être rendu.
Pour débloquer : fournir un git-activity.json valide à la racine du dossier de profil
                 (profiles/galahad/).
```

Le libellé canonique `non évaluable` est donc sur la première ligne, icône **et** mot — avec sa
capitale de début de ligne, `Non évaluable`, et c'est la seule différence tolérée : le contrôle du
§ 12 compare le libellé sans tenir compte de cette capitale initiale. Puis,
inchangés : *Ce qui a été lu* (l'identité si `profile.json` était lisible) et *Notes*. Ni frise,
ni niveau, ni geste : il n'y a rien à en dire, et en fabriquer serait mentir.

Deux précisions, l'une et l'autre nécessaires pour que la règle 4 reste vraie ici :

- **la pièce manquante est nommée par son chemin, et c'est son pointeur** : il n'y a pas de champ
  à citer dans un fichier qu'on n'a pas pu lire. C'est le seul endroit de la sortie où une
  affirmation se réfère à une pièce plutôt qu'à un couple champ = valeur, et le contrôle du § 12
  l'exclut nommément, comme la légende du § 4 ;
- **`profile.json` illisible** : la ligne 1 se réduit à `⛔ Non évaluable`, sans identité, et
  *Ce qui a été lu* dit que rien n'a pu l'être — comportement actuel, conservé.

## 7. Table des gestes (`Recommendation`)

Une table écrite d'avance, un geste par (axe, niveau visé). Pas de rédaction par LLM : la
décision se calcule. **Colonne ajoutée le 2026-08-31 (écart 1.8, seconde moitié)** : la cellule de
la grille (spec 00 § 2) dont chaque geste découle — confrontation faite ligne à ligne le
2026-08-31 entre cette table, `src/Domain/Progression/RecommendationTable.php` et la grille.

| Axe | Vers | Geste | Cellule de la grille dont il découle |
|---|---|---|---|
| Harness | Blue | écrire et versionner un fichier mémoire à la racine du dépôt (conventions, architecture, ce qu'il ne faut pas toucher) et le tenir à jour à chaque erreur répétée | Blue, « context engineering » |
| Harness | Green/Copper | ajouter au moins une règle, un agent ou un hook versionné, et câbler le hook dans la configuration pour qu'il s'exécute sans coopération du modèle | Green et Copper, « context engineering, behavior » |
| Harness | Silver/Gold | ajouter une relance automatique bornée (N essais visibles) dans la CI ou un script, sur une commande du projet | Silver et Gold, « + boucles » |
| En parallèle | Copper+ | isoler chaque chantier (worktree ou équivalent) et mener au moins trois fronts en même temps, habituellement — après le harness | Copper à Gold, « 3 » |
| Intervention | Blue | écrire ce qui est attendu avant de générer (cas limites inclus) pour que les corrections après ouverture diminuent | Blue, « après coup, sur une partie » — par le signal qui la constate |
| Intervention | Green/Copper | tests avant le code et validation de la compréhension avant la première ligne ; remonter une correction répétée dans les règles plutôt que dans le code | Green et Copper, « aux étapes clés » |
| Intervention | Silver | automatiser la validation (tests, lint, duplication) pour qu'aucune reprise humaine ne soit nécessaire après ouverture | Silver, « jamais, une fois la tâche cadrée » |
| Taille | tout niveau | ne rien décréter : la taille habituelle monte quand le dispositif tient ; geste renvoyé à Harness | **aucune** — refus décidé par la règle 5 : la taille ne se décrète pas |
| Harness | Red (depuis White) | commencer à produire avec l'IA sur de vraies tâches et signer ses commits (`Co-Authored-By`) — c'est le premier fait mesurable | Red, « prompts » — par le signal qui la constate (`commits.ai_coauthored_ratio`, spec 02) |
| Intervention | Red (depuis White) | même geste que Harness → Red : rien à reprendre tant que rien n'est produit avec l'IA | Red, « après coup, sur la majorité » — par le même signal |
| En parallèle | Red à Green (depuis White) | mener un chantier avec l'IA jusqu'au merge ; un seul suffit jusqu'à Green | Red à Green, « 1 » |
| Intervention | Gold | aucun geste : l'axe plafonne à Silver par construction — « cadrage compris » n'est pas observable dans les pièces fournies | Gold, « jamais, cadrage compris » — **non observable** |

Complété le 2026-08-29 (remarque Codex sur la PR #22) : la table couvre toutes les paires (axe,
niveau visé) que la règle du minimum peut produire ; aucune ne lève d'exception.

**La colonne « Geste » est le texte rendu, mot pour mot** : rien n'y est de la glose. Sur
Intervention → Gold, la recommandation **nomme le plafond, elle n'invente pas de tâche** — c'est
la conséquence de la spec 03, et c'est écrit ici, hors de la cellule, pour que la cellule reste
comparable caractère à caractère à la constante du domaine (§ 12, test 13).

**Ce que la confrontation du 2026-08-31 établit**, et qui est le résultat demandé par l'écart
1.8 : chaque geste découle d'une cellule nommée de la grille, **sauf deux, et les deux sont des
décisions écrites** — Taille (refus, règle 5) et Intervention → Gold (plafond, spec 03). Trois
gestes ne reprennent pas les mots de leur cellule mais **le signal par lequel la cellule se
constate** (Harness → Red, Intervention → Red, Intervention → Blue) : c'est écrit ici pour qu'on
ne les prenne pas pour des inventions, et parce qu'un geste qui recopierait les mots de la cellule
(« faire des prompts ») ne serait pas actionnable.

**La preuve attendue.** Chaque geste porte le **champ qui devra bouger** pour le valider —
c'est le pointeur de la « prochaine quête », avec les identifiants JSON exacts :

| Geste | `proofField` |
|---|---|
| Harness → Red (depuis White), Intervention → Red (depuis White) | `commits.ai_coauthored_ratio` |
| Harness → Blue | `context_files.agents_md` |
| Harness → Green/Copper | `context_files.rules_count`, `skills_count`, `hooks_count`, `agents_count` (le premier non nul suffit) |
| Harness → Silver/Gold | `repo-context/ › bounded retry` |
| En parallèle | `parallelism.median_concurrent_branches` |
| Intervention (Blue et au-delà) | `pull_requests.median_correction_commits_after_open` |
| Taille | le signal **qui a décidé** : `pull_requests.median_files_changed`, ou `median_lines_changed` en repli — c'est le verdict qui le dit, pas la table |

`Recommendation` porte ce champ (`proofField`).

**Signal absent d'abord.** Une paire (axe, Red) ne vient pas toujours du filtre White : un
axe dont le signal est absent rend `Range(White, …)` et la règle du minimum vise Red. Dans ce
cas la recommandation n'est pas le geste de la table, c'est **« fournir le champ »** (spec 05
§ *Signal absent*) : le geste s'adresse à celui qui constitue le dossier, pas au développeur.
La table ne s'applique qu'aux verdicts confirmés ou en fourchette par échantillon court.

## 8. Gamification — deux gestes, pas davantage

Tranché : « gamification limitée à deux gestes ». Les deux, après l'amendement du 2026-08-31 :

1. **La frise des sept niveaux** en tête de sortie, chaque niveau avec son icône **et son nom**,
   position marquée par des crochets, condition de passage juste dessous (§ 5.1) ;
2. **La prochaine quête** : le premier geste du plan, marqué « à faire en premier », avec le champ
   qui devra bouger et l'état d'où il part (§ 5.5). **Elle n'a plus de bloc à elle** — un bloc qui
   recopiait le geste n° 1 comptait pour un doublon, pas pour une gamification.

Écarté : badges, scores, comparaison entre profils (la grille n'est pas un classement).

## 9. Le rendu — SymfonyStyle, et rien de plus (écart 1.4)

`symfony/console ^7.4` est déjà une dépendance de production (`composer.json`) et embarque
`Symfony\Component\Console\Style\SymfonyStyle` (vérifié le 2026-08-31 :
`vendor/symfony/console/Style/SymfonyStyle.php`). Le rendu passe par lui. **Termwind est
écarté** : ce serait une dépendance de plus pour un besoin que rien ne démontre ; il ne se
rouvre que sur une exigence de rendu qui ne se fait pas avec `SymfonyStyle`, et cela se
journalise.

Quatre contraintes, chacune avec son motif :

1. **Aucun helper dont la sortie dépend de la largeur du terminal.** Vérifié le 2026-08-31 :
   `SymfonyStyle::__construct()` calcule `lineLength = min(largeur du terminal, MAX_LINE_LENGTH)`
   et `Terminal::getWidth()` lit la variable d'environnement `COLUMNS` ; cette longueur ne sert
   qu'à `createBlock()`, donc à `block()`, `note()`, `warning()`, `success()`, `info()`,
   `comment()`. Ces helpers sont **interdits** ici : ils rendraient les fichiers attendus
   dépendants de `COLUMNS`. Restent autorisés, parce qu'ils écrivent leur argument tel quel :
   `section()`, `text()`, `listing()`, `writeln()`, `newLine()`. Le repli de ligne reste celui du
   rendu, à `MAX_WIDTH` (§ 2), exception de pointeur comprise.
   **Les titres de bloc passent par `section()`** — soulignés d'une ligne de tirets, c'est ce que
   le helper produit, et c'est accepté tel quel (tranché le 2026-08-31). **`title()` est
   proscrit** : son soulignement en `=` sous la ligne d'identité alourdit l'en-tête sans rien
   distinguer de plus.
2. **La sortie reste une chaîne comparable octet à octet.** `TextRenderer` écrit dans un
   `SymfonyStyle` construit sur la sortie qu'on lui donne ; les tests lui donnent une sortie
   tamponnée **sans décoration**, et comparent le tampon aux fichiers de `tests/expected/`.
3. **La couleur ne porte jamais une information seule** : tout élément coloré porte aussi une
   icône et un mot (lecteur daltonien). Sans décoration, la sortie dit exactement la même chose.
4. **Le texte venu du profil est échappé avant d'entrer dans un helper de style.** Motif, et
   c'est une non-régression : un `<` dans une identité, une note ou un claim serait lu comme une
   balise de formatage et disparaîtrait silencieusement (remarque 1 de la revue Codex sur la
   PR #25, aujourd'hui tenue par `OUTPUT_RAW` dans `EvaluateCommand`). Le test correspondant est
   conservé (§ 12).

## 10. Ce que la sortie n'emploie plus

Table de traduction, écrite pour que la revue puisse la vérifier d'un `grep` sur les fichiers
attendus :

| Avant | Après | Écart |
|---|---|---|
| `axe bloquant : …` (ligne d'en-tête) | supprimée — la condition de passage du § 5.1 la remplace et la ligne était un doublon du titre *Ce qui a mené là* | 1.1 |
| `(ex æquo)` | les axes nommés un par un, plus la phrase « chacun doit monter » | 1.1 |
| `niveau visé : X` | `Niveau suivant : X` **et sa condition de passage** | 1.5 |
| frise d'icônes nues | frise icône **+** nom | 1.2 |
| le libellé de statut rendu **seul** | `Fiabilité : <libellé canonique> — <ce qu'il veut dire>` ; les trois libellés eux-mêmes sont **inchangés** (§ 6) | 1.7 |
| bloc `Prochaine quête` | premier élément de *Comment monter d'un cran*, marqué | 1.8 |
| bloc `Incertitude sur les autres axes` | fondu dans la ligne *Fiabilité* (les axes nommés) et dans les entrées d'axe du § 5.4 (fourchette et manque chiffré) | 1.6, 2.3 |
| lignes de pointeur sans phrase | une phrase par `Evidence`, pointeur en appui | 1.1, 1.9 |
| intertitre `Acquis pour X` | ligne de synthèse des quatre axes, puis `Déjà acquis pour X` | 1.6 |

## 11. Sorties du chantier

| Fichier | Ce qui change |
|---|---|
| `src/Domain/` — évaluateurs des quatre axes | les phrases des `Evidence` portent l'échelle (§ 3) ; aucun seuil n'est déplacé |
| `src/Domain/SourceGlossary.php` | **créé** — la table des légendes du § 4 |
| `src/Domain/Progression/RecommendationTable.php` | **une constante** : `INTERVENTION_GOLD` est réalignée **mot pour mot** sur la cellule Intervention → Gold de la table du § 7, comme le docblock de la classe le promet déjà pour toutes les autres. Écart constaté le 2026-08-31, corrigé ici parce que c'est ce chantier qui touche la table |
| `src/Application/EvaluateProfileHandler.php` | les deux `Evidence` du filtre White (`whiteVerdicts()`) passent au format phrase + échelle (§ 2, invariant 4) ; aucune autre modification du handler |
| `src/Infrastructure/Render/TextRenderer.php` | la sortie du § 5, la grammaire du § 2, `SymfonyStyle` (§ 9) |
| `src/Infrastructure/Console/EvaluateCommand.php` | passe sa sortie au rendu ; l'échappement du § 9, contrainte 4 |
| `tests/expected/evaluated.txt`, `low-confidence.txt`, `not-assessable.txt` | **les trois sont réécrits**, chacun recopié depuis la sortie du rendu, jamais à la main. Ces trois-là figent le rendu d'`Assessment` **construits dans le test**, pas d'un profil du dépôt (vérifié le 2026-08-31 : `TextRendererTest` les fabrique) |
| `tests/expected/arthur.txt` | **créé** — le seul instantané d'une sortie **réelle** (§ 12, test 14) |
| `tests/` | les tests du § 12 |
| `docs/sortie.md` | **réécrit**, et **régénéré depuis une exécution réelle** en même temps que l'instantané `arthur` : la sortie annotée bloc par bloc, les trois statuts et leur ligne de fiabilité (règle 10 d'`AGENTS.md`, table du § 7.5 de la spec 00). Sa divergence actuelle avec `tests/expected/evaluated.txt` — claims et notes différents, constatée le 2026-08-31 — est connue et **résorbée par cette PR** : le panneau se recopie d'un `make evaluate arthur`, jamais à la main |
| `fixtures/gold-unreachable/README.md` | **une ligne** : le plafond d'Intervention n'est plus attendu en note mais sur la ligne *Niveau suivant* (§ 5.1 et § 5.6, règle 1) |
| `README.md` § *La sortie* | l'extrait devient **le bloc d'en-tête entier** (§ 5.1), recopié de la même exécution jusqu'à la première ligne vide, et la liste « un bloc par ligne » suit les blocs du § 5 |
| `docs/specs/00-vue-ensemble.md` § 7.5 et § 7.7 test 2 | **deux phrases d'appui**, à aligner sur le § 7.3 amendé : « le `README.md` embarque les quatre premières lignes d'une sortie réelle » devient le bloc d'en-tête, et les deux lignes citées en exemple du préfixe commun README / `docs/sortie.md` (`axe bloquant : …`, `Niveau atteint : …`) sont remplacées par deux lignes du nouvel en-tête. Ni la table du § 7.5 ni le test 2 ne changent |

Le § 7.3, point 3 de `docs/specs/00-vue-ensemble.md` est **déjà amendé** par la PR qui porte cette
spec : l'extrait du `README.md` y est le bloc d'en-tête entier, et non plus « les quatre premières
lignes ». Motif : l'en-tête du § 5.1 a quatre lignes logiques dont une se replie, et couper à la
quatrième ligne physique couperait une phrase.

Deux contraintes de découpage, imposées par `AGENTS.md` :

- **règle 7** : un commit ne touche jamais `src/Domain/` et `src/Infrastructure/` ensemble — le
  chantier se commit au minimum en domaine, application, infrastructure, tests, documentation ;
- les commentaires de `src/` qui citent un § de cette spec par son titre suivent les titres
  ci-dessus : un pointeur pendant est un défaut au même titre qu'ailleurs.

`docs/calibration.md` reste vrai par construction : aucun niveau, aucune fourchette, aucun statut
ne change — seule leur mise en mots change. Il est **revérifié** avant merge, comme la définition
de fini l'exige.

## 12. Tests

Les tests existants qui figent un invariant sont **conservés** : rendu comparé au fichier attendu
pour les **trois** `Assessment` construits par `TextRendererTest` (`évalué`, `évalué, confiance
basse`, `non évaluable`) ; ex æquo `arthur` rendu sans moyenne ; ordre Harness > En parallèle >
Intervention > Taille ; `non évaluable` qui nomme son prérequis et ce qui a été lu quand même.
Deux d'entre eux changent d'énoncé, et c'est dit ici plutôt que découvert à l'exécution :

- « aucune ligne au-delà de `MAX_WIDTH` » devient « aucune ligne au-delà de `MAX_WIDTH`,
  **sauf les lignes de pointeur**, qui n'ont ni repli ni troncature » (§ 2, invariant 3) ;
- « aucun pointeur coupé par un repli » est **renforcé** : un pointeur n'est jamais replié, quelle
  que soit sa longueur.

S'y ajoutent :

1. **Une phrase par pointeur, un pointeur par phrase** (§ 2). **Portée du contrôle, close** : il
   ne porte que sur les lignes rendues **depuis une `Evidence` ou une `Note`**. Sont hors champ,
   parce qu'elles ne sont pas des affirmations sur le profil : les cinq lignes de l'en-tête
   (§ 5.1), la frise, la ligne *Fiabilité*, les titres de bloc et leur soulignement, les
   intertitres, les lignes de tête d'axe, les intitulés de famille de notes, et les lignes du plan
   de progression (numéro, geste, « Ce qui le prouvera », « pour trancher »). Sont exclues
   nommément, alors même qu'elles ressemblent à des affirmations : la légende du § 4, le tableau de
   synthèse du § 5.4 (bordures, en-têtes et cellules comprises), et les lignes de `non évaluable` qui nomment une pièce plutôt qu'un champ
   (§ 6.2). Dans ce périmètre : le nombre de lignes contenant ` › ` égale le nombre d'`Evidence`
   et de `Note` rendues, et aucune ligne de pointeur n'est la première ligne de son entrée.
2. **Toutes les `Evidence` d'un axe bloquant sont rendues** — le défaut corrigé était d'en perdre
   toutes sauf la première : un verdict à trois `Evidence` rend trois phrases et trois pointeurs.
   Et le doublon ne revient pas : sur `fixtures/white`, les deux `Evidence` partagées par les
   quatre axes (`whiteVerdicts()`) ne sortent **qu'une fois** (§ 2, invariant 5).
3. **Chaque chiffre porte son échelle** (§ 3), testé côté domaine, par axe et par bande : la
   phrase de l'`Evidence` contient la valeur du seuil nommé qui borne la bande, **lue depuis la
   constante**, jamais réécrite dans le test.
4. **Les quatre axes sont dans la sortie** (§ 5.4) : le tableau de synthèse les porte tous les
   quatre, une rangée par axe dans l'ordre de `RecommendationPolicy::AXIS_ORDER`, chacun avec son niveau (icône +
   nom), `← niveau global` sur ceux — et seulement ceux — que `Assessment::$cappingAxes` désigne, et
   `(incertain)` sur ceux — et seulement ceux — dont la confiance est un `Range` ; chaque axe qui
   ne bloque pas se retrouve ensuite sous `Déjà acquis`, avec son niveau et au moins un pointeur.
5. **Un axe en fourchette qui ne bloque pas ne perd rien** (§ 5.4) : sur `fixtures/short-sample`,
   Intervention et Taille — qui ne plafonnent pas — portent chacune leur mention `(incertain)`,
   leur ligne `fourchette : entre … et …` et leur ligne `pour trancher : …` ; la ligne *Fiabilité*
   les nomme toutes les deux, en plus de l'axe qui plafonne.
6. **La condition de passage** (§ 5.1), un cas par ligne de la table du § 5.1 : `perceval` (un
   seul axe bloquant) ne rend pas la phrase de la règle du minimum ; `arthur` (deux axes) la rend
   et nomme les deux ; la fixture `gold-unreachable` rend Gold « hors d'atteinte ici ». Le cas
   « déjà Gold » **n'a pas de fixture et n'en aura pas** : Gold global exige Intervention à Gold,
   inatteignable par construction (spec 03) — la branche se teste sur un `Assessment` construit
   dans le test, comme la garde qu'elle est.
7. **La légende sort une fois** (§ 4) : sur un profil qui cite `git-activity.json` dix fois, la
   légende apparaît exactement une fois, avant le premier pointeur qui cite la pièce ; une pièce
   hors table ne produit aucune légende.
8. **Le vocabulaire retiré ne revient pas, les libellés canoniques restent** (§ 10) :
   `axe bloquant`, `ex æquo`, `niveau visé`, `Prochaine quête` et `Incertitude sur les autres
   axes` sont absents des fichiers attendus ; et chacun des trois libellés — `évalué`,
   `évalué, confiance basse`, `non évaluable` — est **présent** dans le fichier attendu de son
   statut, mot pour mot, à la capitale initiale près pour `non évaluable` (§ 6.2) — c'est la
   décision figée de la règle 6 d'`AGENTS.md` rendue vérifiable.
9. **Les notes sont dédupliquées** (§ 5.6) : aucun pointeur n'apparaît deux fois dans le bloc
   *Notes* ; aucun pointeur du bloc *Notes* n'apparaît déjà dans un bloc précédent ; un intitulé
   de famille apparaît au plus une fois.
10. **Les cas dégradés disent les trois choses** (§ 6.1) : sur `fixtures/absent-signals`, la
    sortie nomme le champ manquant avec son pointeur `= absent`, dit ce qu'il empêche, et son
    premier geste est « fournir le champ » ; sur `fixtures/short-sample`, elle chiffre le manque
    en PR **et** nomme le plancher.
11. **La sortie ne dépend pas du terminal** (§ 9, contrainte 1) : le rendu est identique avec
    `COLUMNS=40` et avec `COLUMNS=200`.
12. **Le texte du profil n'est pas mangé par le formateur** (§ 9, contrainte 4) : un `<` dans une
    note et dans une identité ressort tel quel — et, la valeur d'un pointeur pouvant recopier une
    note de profil, la ligne longue qui en résulte n'est ni repliée ni tronquée (§ 2,
    invariant 3).
13. **Le geste rendu est celui de la table** (§ 7) : pour chaque paire (axe, niveau visé),
    `RecommendationTable::gestureFor()` rend le texte de la cellule correspondante **mot pour
    mot** — Intervention → Gold compris, qui ne l'était pas avant ce chantier.
14. **Un instantané de sortie réelle** — test **neuf**, et c'est le seul du chantier à partir d'un
    profil du dépôt : la sortie de `arthur` rendue par le câblage complet (`ApplicationFactory`)
    est comparée à `tests/expected/arthur.txt`. Motif du choix, écrit pour qu'on ne le rejoue
    pas : les trois fichiers attendus historiques figent des `Assessment` construits à la main
    (vérifié le 2026-08-31), et `CalibrationTest` ne compare que le niveau et les axes plafonnants
    — **aucun test ne regardait la sortie réelle d'un profil**, ce qui est précisément la chose
    que ce chantier prétend améliorer. Un seul profil est figé, `arthur`, parce que c'est celui
    que `docs/sortie.md` et le `README.md` recopient : le test devient le garde-fou du point de
    revue 10 (« la doc suit le code »). Les trois autres profils calibrés restent couverts par
    `CalibrationTest`, sans instantané — figer quatre sorties ferait payer chaque reformulation de
    phrase quatre fois, sans rien prouver de plus.
