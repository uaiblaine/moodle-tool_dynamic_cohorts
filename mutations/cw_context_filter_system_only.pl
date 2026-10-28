# CW: the context filter is offered on category pages too.
s#if \(\$context->contextlevel == CONTEXT_SYSTEM\) \{\n            \$this->add_filter_from_entity\(.cohort:context.\);#if (true) {\n            \$this->add_filter_from_entity('cohort:context');#s;
