# BO: every configured condition finds every event relevant.
s#if \(!\$instance \|\| \$instance->is_relevant_event\(\$event\)\) \{#if (true) {#s;
