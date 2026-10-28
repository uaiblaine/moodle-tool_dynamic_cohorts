# CE: a template synced from the rule's own cohort does not break the condition.
s#return \$this->get_self_synced_template\(\) !== null;#return false;#s;
