# Composer Phar Bin plugin

The plugin replace development dependencies by their Phar.

## Why ?

I encounter several times a dependency lock on dev tool (code quality, documentation generation, etc.), and most of the time those tool have a Phar binary.

To manage those Phar, solution already exist, like [Phive](https://phar.io/), but it's an another tool to install, another set of commands to run.

So, I create this Composer plugin to integrate Phive into Composer in the less visible way.

## How it's works ?

When run `composer install`, `composer update`, or `composer require`, the plugin will check if any of your _`require-dev`_ packages exist in the Phive repository.
If found then Phive will link the Phar in your composer binaries so scripts will continue to work and the dependency will be ignore (so its sub-dependencies).

## Installation

Simply run: `composer require macfja/composer-phar-bin`, and then a `composer install`.  
Or globally: `composer global require macfja/composer-phar-bin`.

Any later `composer install`, `composer update`, or `composer require` will trigger the dependencies replacement.

## How prevent a package to be replace ?

If for some reason need a package known by Phive to not be replace by its Phar, you can add in you (root) `composer.json` a list of package to don't replace.

In the `extra` section add `composer-phar-bin` object that contains an array named `exclude` of Composer packages.

### Example

In the following example, `phpunit/phpunit` will be downloaded and managed by Composer, but `phan/phan` will be replace by its Phar.

```json
{
    "name": "macfja/composer-phar-bin-test",
    "require-dev": {
        "macfja/composer-phar-bin": "^1.0.0",
        "phpunit/phpunit": "^9.1",
        "phan/phan": "^2.7"
    },
    "extra": {
        "composer-phar-bin": {
            "exclude": ["phpunit/phpunit"]
        }
    }
}
```

## Limitation

The plugin only work after been installed. That seem obvious but there are cases which are tedious to understand because of this.

### Dependencies conflicts on new a environment

Let's imagine that you use the plugin and you have _virtually_ some conflict.
Everything work fine, the plugin replace conflicting libraries with their Phar.

Someone want to install the project, but Composer complaint about some packages ("Your requirements could not be resolved to an installable set of packages.").

The reason is probably because you don't commit your `composer.lock` file, so Composer is trying to solve all dependencies, and as our plugin is not yet installed and loaded, it can't do its magic.

#### How to solve this ?

You have several solutions to solve this:
- you can commit your `composer.lock` file
- you can generate it gradually: remove all dev dependencies, then install your project (`composer install`), and now readd all you dev dependencies
- you can install the plugin globally (so it will always be loaded)

### Missing phars on fresh install

You download a project with a `composer.lock`, run `composer install`.

Everything seem ok, but when you first run a composer script, Composer complain about missing file.

The issue here is that the Composer read the `composer.lock` file, and so only install your dependencies, excluding the ones that are manage by our plugin.
But as the plugin is not loaded (because not yet installed) it doesn't install phar.

#### How to solve this ?

You have two options:
- simply rerun `composer install`
- install the plugin globally (so it will always be loaded)

## Contributing

You can contribute to the library.
To do so, you have Github issues to:

 - ask your question
 - request any change (typo, bad code, etc.)
 - and much more...

You also have PR to:

 - suggest a correction
 - and much more... 

### Local installation

First clone the project (either this repository, or your fork),
next run:

```shell script
make install # Install project vendor
make all # Run QA tools + generate docs
```

### Validate your code

When you done writing your code run the following command check if the quality meet defined rule and to format it:

```shell script
make analyze # Run QA tools
```



## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.