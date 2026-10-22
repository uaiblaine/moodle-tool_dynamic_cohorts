# AI: a site-level manager is scoped like a category one.
s{if \(\$context->contextlevel == CONTEXT_SYSTEM \|\| self::can_manage\(\\core\\context\\system::instance\(\)\)\) \{}{if (\$context->contextlevel == CONTEXT_SYSTEM) \{}s;
