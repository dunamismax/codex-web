export function writeSseEvent(
  writer: WritableStreamDefaultWriter<string>,
  event: string,
  payload: unknown,
) {
  return writer.write(`event: ${event}\ndata: ${JSON.stringify(payload)}\n\n`);
}

export function sseHeaders() {
  return {
    "Content-Type": "text/event-stream",
    "Cache-Control": "no-cache, no-transform",
    Connection: "keep-alive",
  };
}
