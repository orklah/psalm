# Pinned dependencies

`<project>/<corpus ref>.lock`: the composer.lock both Psalm versions install for that corpus commit. A run without one resolves with `composer update` and puts the lock in the `version-diff-report` artifact (`locks/`); commit it here.
