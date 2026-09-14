<?php

namespace App\Services;

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConnectionService
{
    /**
     * @return array{connection: Connection, created: bool, status: string, just_accepted: bool}
     */
    public function request(User $actor, User $target): array
    {
        if ($actor->is($target)) {
            throw new AuthorizationException('You cannot connect with yourself.');
        }

        $outgoing = Connection::query()
            ->where('requester_id', $actor->id)
            ->where('addressee_id', $target->id)
            ->first();

        $incoming = Connection::query()
            ->where('requester_id', $target->id)
            ->where('addressee_id', $actor->id)
            ->first();

        if ($outgoing?->isAccepted() || $incoming?->isAccepted()) {
            $connection = $outgoing?->isAccepted() ? $outgoing : $incoming;

            return [
                'connection' => $connection,
                'created' => false,
                'status' => 'accepted',
                'just_accepted' => false,
            ];
        }

        if ($incoming?->isPending()) {
            $this->accept($actor, $incoming);

            return [
                'connection' => $incoming->fresh() ?? $incoming,
                'created' => false,
                'status' => 'accepted',
                'just_accepted' => true,
            ];
        }

        if ($outgoing?->isPending()) {
            return [
                'connection' => $outgoing,
                'created' => false,
                'status' => 'pending_outgoing',
                'just_accepted' => false,
            ];
        }

        if ($outgoing !== null) {
            $outgoing->forceFill(['status' => ConnectionStatus::Pending])->save();
            Notify::connectionRequest($target, $actor, $outgoing);

            return [
                'connection' => $outgoing,
                'created' => true,
                'status' => 'pending_outgoing',
                'just_accepted' => false,
            ];
        }

        $connection = Connection::query()->create([
            'requester_id' => $actor->id,
            'addressee_id' => $target->id,
            'status' => ConnectionStatus::Pending,
        ]);

        Notify::connectionRequest($target, $actor, $connection);

        return [
            'connection' => $connection,
            'created' => true,
            'status' => 'pending_outgoing',
            'just_accepted' => false,
        ];
    }

    public function accept(User $actor, Connection $connection): Connection
    {
        $this->assertAddressee($actor, $connection);

        if ($connection->isAccepted()) {
            return $connection;
        }

        if (! $connection->isPending()) {
            throw new HttpException(422, 'This connection request is no longer pending.');
        }

        $connection->loadMissing('requester');

        $connection->forceFill(['status' => ConnectionStatus::Accepted])->save();

        $connection->notifications()
            ->where('user_id', $actor->id)
            ->where('type', 'connection_request')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $requester = $connection->requester;

        if ($requester !== null) {
            Notify::connectionAccepted($requester, $actor, $connection);
        }

        return $connection;
    }

    public function reject(User $actor, Connection $connection): Connection
    {
        $this->assertAddressee($actor, $connection);

        if (! $connection->isPending()) {
            throw new HttpException(422, 'This connection request is no longer pending.');
        }

        $connection->forceFill(['status' => ConnectionStatus::Rejected])->save();

        $connection->notifications()
            ->where('user_id', $actor->id)
            ->where('type', 'connection_request')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $connection;
    }

    private function assertAddressee(User $actor, Connection $connection): void
    {
        if ($connection->addressee_id !== $actor->id) {
            throw new AuthorizationException('This connection request is not for you.');
        }
    }
}
