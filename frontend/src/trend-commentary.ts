// Decorative commentary only; never used by the voting engine.
export const trendPhrases = [
  "Our rolling average utilizes a multi-layered blockchain regression to capture the macro-momentum of individual voter bias.",
  "We mapped a predictive neural slope across the coordinate spectrum to forecast the inevitable trajectory of the remaining ballots.",
  "This linear trendline successfully flattens the statistical variance to achieve an optimized 0.99 p-value.",
  "By cross-validating the raw ranking outputs against themselves, the algorithm detected a highly significant directional vector.",
  "We synthesized a deep-learning trend matrix to adjust for seasonal sentiment fluctuations in the voting pool.",
  "The trajectory proves a 94% algorithmic confidence interval, assuming the underlying data remains fully non-linear."
];
export function chooseTrendPhrase(random: () => number = Math.random): string {
  return trendPhrases[Math.min(trendPhrases.length - 1, Math.max(0, Math.floor(random() * trendPhrases.length)))];
}
