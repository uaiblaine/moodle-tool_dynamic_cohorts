# CA: a deleted course does not break the one-course source.
s#return !\$DB->record_exists\('course', \['id' => \$this->get_int\('courseid', 0\)\]\);#return false;#s;
