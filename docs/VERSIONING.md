# Versioning

Lawa't Kape uses a four-segment version: `MAJOR.MINOR.PATCH.BUILD`.

```
1.8.0.122
│ │ │ └── build   — every commit
│ │ └──── patch   — a batch of fixes verified on the live system
│ └────── minor   — a capability someone would notice
└──────── major   — the system is a different thing
```

Three of those segments describe *what changed*. The fourth just counts.

## Where it lives

One place: the `version` field in `composer.json`.

`config/app.php` reads it from there, and the sidebar badge in both shells reads
`config('app.version')`. Editing `composer.json` is the whole job.

It used to be maintained by hand in all three, which is how a badge ends up
disagreeing with what is actually deployed. `VersioningTest` fails if a second
hard-coded version string reappears in the views.

On a deployed box the value is resolved when `php artisan config:cache` runs, so
**a release is not visible in the UI until the config cache is rebuilt** — which
the deploy does anyway.

## When to move each segment

### BUILD — every commit

Always. Never reset it, not even when another segment moves: `1.8.0.122` is
followed by `1.9.0.123`, not `1.9.0.0`.

This number is what someone reads off the sidebar to answer "am I on the latest
build?". If it restarts, two different builds can share a number and that check
stops meaning anything.

### PATCH — a batch of fixes, verified live

Not "I fixed something" — that is a build bump. Move the patch when you reach a
state you would *return to*: the fixes are on the live system and have survived
real service.

A useful objective trigger is **a migration**. Anything that changes the database
is a boundary you cannot cross backwards with `git revert` alone, so it deserves
a number you can point at.

### MINOR — a capability someone would notice

The test is whether you could stand in front of the shop's system and say
"that's new". Adaptive bandwidth shaping was a minor. Notification dismissal was
a minor. The mobile drawer fix was not — that was an existing feature finally
working.

Reset PATCH to 0 when MINOR moves. Do not reset BUILD.

### MAJOR — the system is a different thing

A re-architecture significant enough that the project would need re-explaining
from scratch. Realistically this stays at `1` for the life of the capstone.

## Cutting a release

1. Edit `version` in `composer.json`.
2. Add the entry to `CHANGELOG.md`.
3. Commit; the subject ends with `(v<version>)`.
4. Tag it: `git tag -a v1.9.0 -m "..."` — tag the MINOR/PATCH release, not every
   build. 121 build tags would bury the nine that matter.
5. `php artisan config:cache` on the box, or the badge keeps showing the old one.

## A note on the history

Builds 0 through 121 all carried `1.0.0.x`: the build counter moved on every
commit and the other three segments never did, so the version recorded how many
changes had shipped and nothing about what they were.

The minor versions for that period were assigned retrospectively when this scheme
was adopted at build 122, and are tagged at the commits where each line of work
finished. The build numbers in those commit subjects are original and continuous
— only the tags and `CHANGELOG.md` are backfilled. Nothing in the history was
rewritten.
