PLUGIN_NAME = wpc-buy-now-button
WP_DIR = /var/www/html
WP_CONTAINER = wordpress-wordpress-test
DB_CONTAINER = db-wordpress-test
PLUGIN_DIR = $(WP_DIR)/wp-content/plugins/$(PLUGIN_NAME)

.PHONY: default
default: help

# Display help
.PHONY: help
help: ## Display this help message
	@echo "Available targets:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)


.PHONY: build
build: ## Build plugin zip file
	@$(MAKE) clean
	cd .. && zip -r $(PLUGIN_NAME).zip $(PLUGIN_NAME)/$(PLUGIN_NAME).php $(PLUGIN_NAME)/index.php $(PLUGIN_NAME)/src $(PLUGIN_NAME)/languages $(PLUGIN_NAME)/assets  $(PLUGIN_NAME)/readme.txt  $(PLUGIN_NAME)/includes

.PHONY: clean
clean: ## Remove existing zip file
	@rm -f ../$(PLUGIN_NAME).zip


.PHONY: pot
pot: ## Generate pot file
	@xgettext --default-domain=wcs-flarum --language=PHP --keyword=__ --keyword=_e --keyword=_n:1,2 --keyword=_x:1c,2 \
  --keyword=_ex:1c,2 --keyword=_nx:4c,1,2 --keyword=esc_attr__ --keyword=esc_attr_e \
  --keyword=esc_attr_x:1c,2 --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_html_x:1c,2 \
  --from-code=UTF-8 \
  --add-comments=translators \
  -o languages/test.pot \
  $(shell find . -name "*.php")
	@echo "Pot file generated."

.PHONY: merge-pot
merge-pot: ## Merge pot file with existing po files
	@msgmerge --update languages/$(PLUGIN_NAME).pot languages/test.pot
	@echo "Pot file merged with existing po files."

.PHPNY: install-wp-tests
install-wp-tests: ## Install WordPress test suite
	@docker exec -w $(PLUGIN_DIR) $(WP_CONTAINER) \
		bash bin/install-wp-tests.sh wp_test root root $(DB_CONTAINER)

.PHONY: test
test: ## Run tests
	@docker exec -w $(PLUGIN_DIR) $(WP_CONTAINER) \
		vendor/bin/phpunit

.PHONY: sh
sh: ## Start shell in Docker container
	@docker exec -it -w $(PLUGIN_DIR) $(WP_CONTAINER) bash
