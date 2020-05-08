# composer-versions-check

composer-versions-check is a plugin for Composer.

It warns user for outdated packages from last major versions after update command.

[![Latest Stable Version](https://poser.pugx.org/sllh/composer-versions-check/v/stable)](https://packagist.org/packages/sllh/composer-versions-check)
[![Latest Unstable Version](https://poser.pugx.org/sllh/composer-versions-check/v/unstable)](https://packagist.org/packages/sllh/composer-versions-check)
[![License](https://poser.pugx.org/sllh/composer-versions-check/license)](https://packagist.org/packages/sllh/composer-versions-check)
[![Dependency Status](https://www.versioneye.com/php/sllh:composer-versions-check/badge.svg)](https://www.versioneye.com/php/sllh:composer-versions-check)
[![Reference Status](https://www.versioneye.com/php/sllh:composer-versions-check/reference_badge.svg)](https://www.versioneye.com/php/sllh:composer-versions-check/references)

[![Total Downloads](https://poser.pugx.org/sllh/composer-versions-check/downloads)](https://packagist.org/packages/sllh/composer-versions-check)
[![Monthly Downloads](https://poser.pugx.org/sllh/composer-versions-check/d/monthly)](https://packagist.org/packages/sllh/composer-versions-check)
[![Daily Downloads](https://poser.pugx.org/sllh/composer-versions-check/d/daily)](https://packagist.org/packages/sllh/composer-versions-check)

[![Build Status](https://travis-ci.org/Soullivaneuh/composer-versions-check.svg?branch=master)](https://travis-ci.org/Soullivaneuh/composer-versions-check)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Soullivaneuh/composer-versions-check/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Soullivaneuh/composer-versions-check/?branch=master)
[![Code Climate](https://codeclimate.com/github/Soullivaneuh/composer-versions-check/badges/gpa.svg)](https://codeclimate.com/github/Soullivaneuh/composer-versions-check)
[![Coverage Status](https://coveralls.io/repos/Soullivaneuh/composer-versions-check/badge.svg?branch=master)](https://coveralls.io/r/Soullivaneuh/composer-versions-check?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/278a8379-fb6d-425f-b175-7d7b9ef93d47/mini.png)](https://insight.sensiolabs.com/projects/278a8379-fb6d-425f-b175-7d7b9ef93d47)

![composer-versions-check_demo](https://cloud.githubusercontent.com/assets/1698357/14637529/2e32a778-0632-11e6-99c7-0e1c284a7436.gif)

<sup>Screencast provided by [Silentcast](https://github.com/colinkeenan/silentcast).</sup>

## Installation

You can install it either globally (recommended):

```bash
composer global require sllh/composer-versions-check
```

or locally (as require-dev dependency then):

```bash
composer require --dev sllh/composer-versions-check
```

## Usage

That's it! Composer will enable automatically the plugin as soon it's installed.

Just run `composer update` command to see the plugin working.

## Configuration

You can configure the plugin via the [`COMPOSER_HOME/config.json`](https://getcomposer.org/doc/03-cli.md#composer-home) file. Here is the default one:

```json
{
    "config": {
        "sllh-composer-versions-check": {
            "root-packages-only": false,
            "show-links": false
        }
    }
}
```

* `ignore-sub-dependencies`: Shows only outdated root packages.
* `show-links`: Shows outdated package links. Set to `true` to get a larger output, like the demo.
