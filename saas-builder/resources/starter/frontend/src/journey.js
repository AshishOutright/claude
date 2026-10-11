// Helpers for the phase / stage / status journey sent by the Smart Gateway's "meta" action.
export function findStage(journey, phaseKey, stageKey) {
  const phase = journey.find((p) => p.key === phaseKey);
  const stage = phase?.stages.find((s) => s.key === stageKey);
  return { phase, stage };
}

export function journeyLabel(journey, record) {
  const { phase, stage } = findStage(journey, record.journey_phase, record.journey_stage);
  const status = stage?.statuses.find((s) => s.key === record.journey_status);
  return [phase?.label, stage?.label, status?.label].filter(Boolean).join(' · ');
}
