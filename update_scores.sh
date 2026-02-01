#!/usr/bin/env bash

BASE_URL="http://localhost:8080/updateRegularSeasonScores.php"
APPLY=1

for WEEK in {1..18}; do
  echo "Updating scores for week $WEEK..."
  curl -s "${BASE_URL}?week=${WEEK}&apply=${APPLY}"
  echo
done

echo "All weeks processed."

