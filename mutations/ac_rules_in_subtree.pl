# AC: every rule is inside the category.
s{return \$rulecontext->id == \$context->id \|\| str_starts_with\(\$rulecontext->path, \$context->path \. '/'\);}{return true;}s;
