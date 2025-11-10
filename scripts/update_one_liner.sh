#!/bin/bash
# One-liner script untuk update UJAN - Copy paste ke SSH
# Usage: Copy paste script ini ke SSH terminal dan tekan Enter

cd /path/to/ujian && git stash push -m "Auto stash $(date +%Y%m%d_%H%M%S)" 2>/dev/null; git fetch origin master; git checkout master; git reset --hard origin/master; chmod -R 777 cache assets/uploads 2>/dev/null; rm -rf cache/*.json 2>/dev/null; echo "Update selesai! Version: $(git describe --tags --abbrev=0 2>/dev/null || echo 'unknown')"



