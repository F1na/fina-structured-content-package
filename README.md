# fina/structured-content

Infrastructure générique pour du contenu structuré en blocs dans une application Laravel — un équivalent simplifié de Strapi, distribué sous forme de package Composer privé et réutilisable entre plusieurs projets.

Le package fournit :

- le stockage (table `block` + modèle `Block`, association polymorphique),
- le CRUD générique + réordonnancement (`BlockController`),
- l'autorisation (Gate `manageBlocks`, fail-closed par défaut),
- les points d'extension (`BlockTypeRegistry`, `BlocksRegistrar`).

Il ne contient **aucune** logique métier : aucun type de bloc concret propre à un projet, aucun rôle/permission, aucun `instanceof` sur un modèle applicatif. Tout ce qui est spécifique à un projet est enregistré par ce projet lui-même, jamais par le package.

## Installation

```bash
composer require fina/structured-content
```

Le `ServiceProvider` est auto-découvert via la clé `extra.laravel.providers` du `composer.json` du package — rien à ajouter manuellement dans `bootstrap/providers.php` pour le package lui-même.

### Publier la configuration

```bash
php artisan vendor:publish --tag=blocks-config
```

Publie `config/blocks.php`, qui expose notamment `route_prefix` (défaut `api`, configurable aussi via `BLOCKS_ROUTE_PREFIX`).

### Publier et exécuter la migration

```bash
php artisan vendor:publish --tag=blocks-migrations
php artisan migrate
```

Crée la table `block` (`blockableId`/`blockableType` polymorphiques, `type`, `data` JSON, `position`, `createdAt`/`updatedAt`).

### Conventions de nommage BDD

- noms de table au singulier et en camelCase (`block`, pas `blocks` ni `Blocks`),
- noms de colonne en camelCase (`blockableId`, `blockableType`, `createdAt`, `updatedAt`),
- le `_` est réservé aux tables d'association (pivots) — le package n'en a aucune, la relation polymorphique tenant sur deux colonnes de la table `block` elle-même.

Ces conventions ne concernent que le schéma (tables/colonnes) : les routes HTTP (`/{blockableType}/{blockableId}/blocks`) et les clés de configuration (`route_prefix`) restent en anglais/snake_case ou pluriel là où c'est l'usage REST habituel, sans lien avec le nommage BDD.

## Routes exposées

Toutes préfixées par `config('blocks.route_prefix')` :

| Méthode | URI                                             | Action     |
|---------|--------------------------------------------------|------------|
| GET     | `/{blockableType}/{blockableId}/blocks`           | `index`    |
| POST    | `/{blockableType}/{blockableId}/blocks`           | `store`    |
| POST    | `/blocks/{block}/up`                              | `up`       |
| POST    | `/blocks/{block}/down`                            | `down`     |
| PUT     | `/blocks/{block}`                                 | `update`   |
| DELETE  | `/blocks/{block}`                                 | `destroy`  |

`{blockableType}` est une clé arbitraire (ex. `articles`), résolue via `BlocksRegistrar` — jamais un nom de classe.

### API segmentée par guard (`/admin`, `/healthFacility`, ...)

Le groupe de routes ci-dessus est enregistré automatiquement par `BlocksServiceProvider`, sans middleware d'authentification — adapté à une API avec un seul guard. Si ton API est découpée en plusieurs segments fonctionnels ayant chacun leur propre guard, ce groupe unique et non protégé ne convient plus : il faut monter les routes une fois par segment, chacune derrière son propre middleware `auth:<guard>`.

Pour ça, désactive l'auto-registration et monte les routes toi-même via `Fina\StructuredContent\StructuredContent::routes()` (même pattern que `Sanctum::routes()`) :

```php
// config/blocks.php ou .env
'register_routes' => false, // ou BLOCKS_REGISTER_ROUTES=false
```

```php
// routes/admin.php
use Fina\StructuredContent\StructuredContent;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('auth:admin')->group(function () {
    StructuredContent::routes();
});

// routes/healthFacility.php
Route::prefix('healthFacility')->middleware('auth:healthFacility')->group(function () {
    StructuredContent::routes();
});
```

`StructuredContent::routes()` applique lui-même le middleware `SubstituteBindings` nécessaire au model binding implicite (`Block $block`) — pas besoin de le rajouter. Le Gate `manageBlocks` n'a rien à changer : `Auth::user()`/`Gate::denies()` résolvent déjà le bon guard, puisque le middleware `auth:<guard>` appelle `Auth::shouldUse($guard)` avant d'atteindre le contrôleur. Il suffit donc de faire varier la logique du Gate selon la classe de `$user` (`Admin` vs `HealthFacilityUser`) :

```php
Gate::define('manageBlocks', function (?Authenticatable $user, $blockable) {
    return match (true) {
        $user instanceof \App\Models\Admin => true,
        $user instanceof \App\Models\HealthFacilityUser => $user->facility->owns($blockable),
        default => false,
    };
});
```

### Upload de fichiers pour le bloc `image` — pas de media library

Contrairement à Strapi, ce package ne fournit **pas** de media library partagée : chaque bloc qui a besoin d'un fichier gère le sien directement, en une seule requête, sans endpoint d'upload séparé ni ressource réutilisable entre blocs.

```bash
# Création : le fichier est envoyé dans la même requête que le bloc (multipart/form-data)
POST /api/articles/1/blocks
Content-Type: multipart/form-data
type: image
file: <binaire>
alt: Description de l'image

# → { "id": 12, "type": "image", "position": 1, "url": "https://.../storage/blocks/images/xxxx.jpg", "alt": "Description de l'image" }

# Mise à jour sans remplacer le fichier : omettre "file" conserve l'existant
PUT /api/blocks/12
{ "alt": "Nouvelle description" }

# Mise à jour en remplaçant le fichier : l'ancien est supprimé du disque
PUT /api/blocks/12
Content-Type: multipart/form-data
file: <nouveau binaire>
```

Le fichier est supprimé du disque automatiquement quand le bloc est supprimé (`DELETE /api/blocks/12`), ou remplacé (ancien fichier effacé) si un nouveau fichier est envoyé lors d'un update.

Le disque de stockage (`config('blocks.media_disk')`, défaut `public`) et la taille max en Ko (`config('blocks.media_max_size')`, défaut `10240`) sont configurables via `BLOCKS_MEDIA_DISK`/`BLOCKS_MEDIA_MAX_SIZE`.

Ce comportement repose sur trois hooks optionnels du contrat `BlockType` (no-op par défaut dans `AbstractBlockType`), que tout type de bloc métier possédant sa propre ressource externe peut surcharger de la même façon :

- `rulesForUpdate()` — règles de validation à l'update, différentes de `rules()` (ex : fichier optionnel plutôt que requis) ;
- `prepareForStorage(array $validated, Request $request, array $previousData)` — transforme le payload validé juste avant persistance (déplace le fichier uploadé vers le disque, gère le remplacement) ;
- `afterDelete(array $data)` — nettoyage (suppression du fichier) juste avant la suppression du bloc.

## Ce que le projet consommateur doit fournir

Le package ne présume rien sur les modèles ou les rôles de l'application hôte. Trois choses sont à la charge du projet, typiquement dans un provider applicatif (voir [`examples/BlocksServiceProvider.php`](examples/BlocksServiceProvider.php)) :

1. enregistrer ses blockables,
2. enregistrer ses types de blocs métier,
3. surcharger le Gate `manageBlocks` avec la vraie logique d'autorisation.

### ⚠️ Ordre de déclaration des providers

Ce provider applicatif **doit** être déclaré **après** `Fina\StructuredContent\BlocksServiceProvider` dans `bootstrap/providers.php` :

```php
return [
    Fina\StructuredContent\BlocksServiceProvider::class, // ajouté par l'auto-discovery Composer
    App\Providers\BlocksServiceProvider::class, // le vôtre, listé explicitement, APRÈS
];
```

L'auto-discovery Composer charge le provider du package avant tout provider listé explicitement. Comme le package définit un Gate fermé et n'enregistre que ses types génériques dans son propre `boot()`, le provider applicatif doit s'exécuter ensuite pour que sa surcharge du Gate et ses enregistrements dans les registres prennent effet.

### 1. Enregistrer un blockable

```php
use Fina\StructuredContent\BlocksRegistrar;

public function boot(BlocksRegistrar $blockables): void
{
    $blockables->registerBlockable('articles', \App\Models\Article::class);
}
```

Le modèle blockable n'a besoin d'aucune interface particulière côté package.

#### Relation `blocks` (optionnel)

Pour lire les blocs directement depuis le modèle, sans passer par l'API, ajouter le trait `HasBlocks` :

```php
use Fina\StructuredContent\Concerns\HasBlocks;

class Article extends Model
{
    use HasBlocks;
}

Article::with('blocks')->get();   // relation morphMany triée par position, eager-loadable
$article->blocks;                 // Collection de Block (->data brut accessible)
$article->blocks->toArray();      // sérialisé comme l'API (toArray() de chaque type de bloc)
return $article->load('blocks');  // idem dans le JSON de la réponse
```

La sérialisation d'un `Block` (`toArray()`/JSON) reprend le format de l'API ; `createdAt`/`updatedAt` n'y figurent pas.

Le trait supprime aussi les blocs du modèle (et appelle `afterDelete()` de chaque type, ex. suppression des fichiers) quand il est supprimé — définitivement, un soft delete les conserve. Il est compatible avec une morph map : `blockableType` est écrit via `getMorphClass()`.

Pour lister les blockables enregistrés :

```bash
php artisan blocks:blockables
```

Affiche la clé et la classe de chaque blockable whitelisté dans `BlocksRegistrar` — pratique pour vérifier quels `{blockableType}` sont réellement accessibles sans relire tous les `boot()` des providers.

### 2. Enregistrer un type de bloc métier

Étendre `Fina\StructuredContent\AbstractBlockType` (qui implémente `authorizeSave()` en retournant `true` par défaut) :

```php
use Fina\StructuredContent\AbstractBlockType;

class CalloutBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'callout';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'style' => ['required', 'in:info,warning,danger'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'style' => $data['style'],
        ];
    }
}
```

Puis l'enregistrer :

```php
use Fina\StructuredContent\BlockTypeRegistry;

public function boot(BlockTypeRegistry $blockTypes): void
{
    $blockTypes->register(CalloutBlockType::class);
}
```

Le package enregistre déjà trois types génériques par défaut :

| Clé       | Classe                                                  | Champs                                      |
|-----------|----------------------------------------------------------|----------------------------------------------|
| `text`    | `Fina\StructuredContent\BlockTypes\TextBlockType`        | `content` (string, requis)                   |
| `image`   | `Fina\StructuredContent\BlockTypes\ImageBlockType`       | `file` (upload, requis à la création, optionnel à l'update), `alt` (string) — `url` est résolue et ajoutée en lecture |
| `heading` | `Fina\StructuredContent\BlockTypes\HeadingBlockType`     | `text` (string, requis), `level` (1-6, défaut 2) |

`TextBlockType` sert d'exemple concret d'implémentation de `AbstractBlockType`. `HeadingBlockType` (le bloc "Titre") porte un niveau sémantique H1-H6 afin que le front consommateur puisse générer un balisage de titre correct (hiérarchie des `<h1>`-`<h6>`).

Pour restreindre un type précis (ex. réservé aux admins), surcharger `authorizeSave()` — voir [`examples/CalloutBlockType.php`](examples/CalloutBlockType.php).

### Lister les types de blocs enregistrés

```bash
php artisan blocks:types
```

Affiche la clé et la classe de chaque type enregistré dans `BlockTypeRegistry` (les trois types par défaut du package + tous les types métier enregistrés par le projet consommateur). Pratique pour vérifier, dans un environnement donné, quels `type` sont réellement acceptés par les endpoints sans avoir à relire tous les `boot()` des providers.

### 3. Surcharger le Gate `manageBlocks`

Le package définit `manageBlocks` fermé par défaut (`return false`) : sans surcharge, toutes les routes du contrôleur répondent `403`. C'est un choix fail-closed délibéré — le package n'a aucune idée des rôles/permissions de l'application hôte, et un Gate ouvert par défaut serait une faille de sécurité pour tout projet qui oublierait de le configurer.

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

Gate::define('manageBlocks', function (?Authenticatable $user, $blockable) {
    return match (true) {
        $blockable instanceof \App\Models\Article => $user?->can('update', $blockable) ?? false,
        $blockable instanceof \App\Models\Page => $user?->hasRole('editor') ?? false,
        default => false,
    };
});
```

Ce Gate contrôle l'accès général au blockable. `BlockType::authorizeSave()` s'ajoute en complément pour une restriction fine par type de bloc, indépendante du Gate général.

> ⚠️ **Piège Laravel non évident** : le premier paramètre `$user` d'une closure `Gate::define` doit impérativement être typé nullable (`?Authenticatable`). Laravel inspecte la signature de la closure et, pour une requête non authentifiée (guest), **ignore silencieusement le callback** si son premier paramètre n'accepte pas `null` — retombant alors sur un refus implicite, sans jamais exécuter votre logique. Omettre ce type nullable donne l'impression que la surcharge du Gate "ne marche pas" alors qu'elle n'est simplement jamais appelée pour les visiteurs non connectés.

## Tests

```bash
composer install
vendor/bin/pest
```

Utilise [Orchestra Testbench](https://packages.tools/testbench) ; voir `tests/TestCase.php` et les fixtures dans `tests/Fixtures/`.

## Playground de dev

Le package n'a pas d'application Laravel hôte : les tests tournent contre Testbench (`tests/TestCase.php`), sans jamais exposer un vrai serveur HTTP. Pour tester manuellement les routes contre une vraie DB (SQLite) sans créer de projet séparé, le package utilise [Workbench](https://packages.tools/workbench), inclus dans `orchestra/testbench` (pas de dépendance supplémentaire).

```bash
composer run build   # (re)crée workbench/database/database.sqlite et rejoue les migrations
composer run serve   # démarre le serveur sur http://127.0.0.1:8000
```

`workbench/` joue le rôle du provider applicatif que fournirait un vrai projet consommateur (voir `examples/BlocksServiceProvider.php`) :

- `workbench/app/Models/Post.php` — blockable de test, whitelisté sous la clé `posts`,
- `workbench/app/Blocks/CalloutBlockType.php` — type de bloc métier d'exemple (au-delà des trois types par défaut du package),
- `workbench/app/Providers/WorkbenchServiceProvider.php` — enregistre les deux ci-dessus et surcharge le Gate `manageBlocks` en tout-ouvert (sandbox uniquement, ne pas reproduire tel quel côté projet réel).

```bash
# Post#1 existe déjà après `composer run build` (seedé via workbench/database/seeders)
curl http://127.0.0.1:8000/api/posts/1/blocks
curl -X POST http://127.0.0.1:8000/api/posts/1/blocks \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"type":"text","content":"Bonjour"}'
```

`testbench.yaml` liste les providers dans l'ordre attendu (`BlocksServiceProvider` du package, puis `WorkbenchServiceProvider`) et ajoute `database/migrations` (celles du package, normalement publish-only) en plus de `workbench/database/migrations`.
