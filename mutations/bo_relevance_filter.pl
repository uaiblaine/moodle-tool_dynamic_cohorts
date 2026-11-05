# BO: every configured condition finds every event relevant.
s#if \(\$instance->is_relevant_event\(\$event\)\) \{#if (true) {#s;
