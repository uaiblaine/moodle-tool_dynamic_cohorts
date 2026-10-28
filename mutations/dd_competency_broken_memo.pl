# DD: the competency condition reads is_broken() afresh every time.
s#if \(\$this->brokenmemo === null \|\| \$this->brokenmemo\[0\] !== \$key\) \{#if (true) {#s;
