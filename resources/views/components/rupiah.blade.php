@props(['amount'])

<span {{ $attributes }}>Rp {{ number_format((int) $amount, 0, ',', '.') }}</span>
