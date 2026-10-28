# DH: the context filter keeps core's options: every context holding a cohort, one query each.
s#\n                ->set_options_callback\(\[self::class, .get_rule_context_options.\]\);#;#s;
