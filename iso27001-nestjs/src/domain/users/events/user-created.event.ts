/** A.12: Domain event emitted after a user is successfully created. */
export class UserCreatedEvent {
  constructor(
    public readonly userId: string,
    /** A.12: Never log raw email — use its SHA-256 hash */
    public readonly emailHash: string,
    public readonly role: string,
  ) {}
}
