---
paths:
  - 'app/{Mcp,Services}/**'
---

# Mcp Services

## record_episode replaces an episode; it does not add to it
EpisodeRecorder::record() deletes every event for that episode number before re-inserting. That is what makes live entry safe — calling it twice corrects rather than duplicates — but it also means re-recording the finale wipes any placements set by set_final_placements, since those are hung on the last episode number. Re-run set_final_placements after re-recording the finale. Awards to already-eliminated castaways are recorded with a warning, never rejected: a mis-numbered episode is the likelier mistake.
