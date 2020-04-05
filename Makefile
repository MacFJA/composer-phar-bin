SHELL := /bin/bash

.PHONY: install all analyze docs clean composer

install: | vendor

all: analyze docs

analyze: | vendor
	$(COMPOSER) normalize
	touch -m composer.lock
	$(COMPOSER) exec -v parallel-lint -- src
	$(COMPOSER) exec -v php-cs-fixer -- fix
	$(COMPOSER) exec -v phpcpd -- --fuzzy --min-lines=2 --min-tokens=15 --progress src
	$(COMPOSER) exec -v phpmd -- src text phpmd.xml
	$(COMPOSER) exec -v phpa -- src
	$(COMPOSER) exec -v phan -- --allow-polyfill-parser --directory src --unused-variable-detection --dead-code-detection --target-php-version 7.2
	$(COMPOSER) exec -v phpstan -- analyse src
	$(COMPOSER) exec -v psalm -- --show-info=true src

docs: | vendor
	[ -d build ] || mkdir build
	$(COMPOSER) exec -v phploc -- --log-xml=build/phploc.xml src
	$(COMPOSER) exec -v phpdox -- --file phpdox-composer.xml
	$(COMPOSER) exec -v phpdox

clean::
	@echo Remove all generated files
	rm -f composer.phar
	rm -f .php_cs.cache
	rm -f composer.lock
	@echo Remove all generated directories
	rm -rf vendor
	rm -rf build
	rm -rf docs
	# # Or
	# grep -v -E "^(#.+)?$" .gitignore vendor-bin/.gitignore |\
	# 	sed 's/.gitignore://' | sed 's#^#./#' | sed 's#//#/#' |\
	# 	xargs -I % rm -rf %
	# # Or
	# find . -name .gitignore | xargs -I % sh -c  "sed '/^$/d' % | sed '/^#/d' | sed 's#^#%#'" |\
	# 	sed 's#/.gitignore#/#' | sed 's#//#/#' | sed '/!/d' |\
	#	xargs -I % rm -rf %

# Files

vendor: composer.lock
	$(COMPOSER) install --optimize-autoloader --prefer-dist

composer.lock: composer.json
	$(COMPOSER) update --prefer-lowest --prefer-dist

composer.phar:
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	php composer-setup.php --quiet
	rm composer-setup.php

# Check Composer installation
ifneq ($(shell command -v composer > /dev/null ; echo $$?), 0)
  ifneq ($(MAKECMDGOALS),composer.phar)
    $(shell $(MAKE) composer.phar)
  endif
  COMPOSER=php composer.phar
else
  COMPOSER=composer
endif

# Magic "make composer ..." command
ifeq ($(firstword $(MAKECMDGOALS)),composer)
  COMPOSER_ARGS=$(wordlist 2, $(words $(MAKECMDGOALS)), $(MAKECMDGOALS))
  $(eval $(COMPOSER_ARGS):;@:)
endif
composer:
	$(COMPOSER) $(COMPOSER_ARGS)