# H: drop the per-rule capability check in delete_rules().
s{\n            require_capability\('tool/dynamic_cohorts:manage', \$rule->get_context\(\)\);\n}{\n}s;
