@props(['user', 'size' => 'small'])
<span {{ $attributes->class(['rt03-account-avatar', 'rt03-account-avatar--'.$size]) }}>
    <span class="rt03-account-avatar__initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
    @if($user->avatar_path)
        <img class="rt03-account-avatar__image"
             src="{{ route('profile.photo', ['v' => $user->updated_at?->timestamp ?? 0]) }}"
             alt="" loading="lazy" onerror="this.remove()">
    @endif
</span>
