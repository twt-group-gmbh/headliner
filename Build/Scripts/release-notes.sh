#!/usr/bin/env bash
#
# Prints the CHANGELOG.md section of one version - without its "## [x.y.z]" heading - which
# the release workflow publishes as the text of the GitHub release.
#
#   bash Build/Scripts/release-notes.sh 1.1.0
#   bash Build/Scripts/release-notes.sh 1.1.0 path/to/CHANGELOG.md
#   bash Build/Scripts/release-notes.sh --plain 1.1.0   # plain text for the TER upload comment
#
# With --plain the Markdown is reduced to readable text: headings become "Heading:", and
# backticks, **bold** and *italic* markers are dropped (the TER shows the comment as plain text).
#
# Exits non-zero (and prints nothing on stdout) if the changelog has no section for the version.

set -euo pipefail

plain=0
args=()
for argument in "$@"; do
    if [ "${argument}" = "--plain" ]; then plain=1; else args+=("${argument}"); fi
done

version="${args[0]:?usage: release-notes.sh [--plain] <version> [changelog-file]}"
file="${args[1]:-$(dirname "$0")/../../CHANGELOG.md}"

# From the heading of this exact version up to (not including) the next "## [" heading.
notes="$(awk -v v="${version}" '
    /^## \[/ { if (found) exit; if (index($0, "## [" v "]") == 1) { found = 1; next } }
    found { print }
' "${file}")"

# Trim leading blank lines ($(...) already dropped the trailing ones).
notes="${notes#"${notes%%[![:space:]]*}"}"

if [ -z "${notes}" ]; then
    echo "No CHANGELOG section found for version ${version} in ${file}" >&2
    exit 1
fi

if [ "${plain}" -eq 1 ]; then
    notes="$(printf '%s\n' "${notes}" | sed -E \
        -e 's/^#{2,6} +(.*)$/\1:/' \
        -e 's/`//g' \
        -e 's/\*\*([^*]+)\*\*/\1/g' \
        -e 's/\*([^*]+)\*/\1/g')"
fi

printf '%s\n' "${notes}"
