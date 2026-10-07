/** Ob in dieser Sitzung schon eine Eingabe kam – danach bleibt das Zeichen weg. */
export type Begruesst = boolean

declare module 'claude-code' {
  interface PluginState {
    'vecom-stil': { begruesst: Begruesst }
  }
}
