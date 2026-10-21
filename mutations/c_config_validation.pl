# C: never ask the condition to validate its config data.
s{\$errors = \$instance->config_form_validate\(\$configdata\);}{\$errors = [];}s;
