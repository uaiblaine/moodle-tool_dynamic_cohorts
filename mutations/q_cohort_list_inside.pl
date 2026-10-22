# Q: every cohort counts as inside the page's context.
s{\$inside = \$context->id == \$within->id \|\| str_starts_with\(\$context->path, \$within->path \. '/'\);}{\$inside = true;}s;
