# Data Attribution

The administrative dataset in this directory (provinces, districts,
municipalities, ward counts, and their Nepali names) is derived from
[sagautam5/local-states-nepal](https://github.com/sagautam5/local-states-nepal),
used under the MIT License.

Copyright (c) 2020 Sagar Gautam

Upstream data is vendored unmodified in `database/data/upstream/`, alongside a
copy of its MIT license. The canonical files here are generated from it by
`php artisan nepal:build-dataset`.

The only editorial change is romanized spelling: where upstream and this
project spelled a place differently, this project's existing spelling is
preserved as `legacy_name` so published API responses stay stable.
