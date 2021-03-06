<?php
namespace esperecyan\webidl\lib;

/** @internal */
class IntegerType
{
    use Utility;
    
    /** @var int PHP がサポートする整数型の最小値。 */
    private static $phpIntMin = ~PHP_INT_MAX;
    
    /**
     * 与えられた値が整数型に変換可能であれば真を返します。
     *
     * 次の型の値が整数型に変換可能であるとみなされます。
     * 論理型。整数型。浮動小数点型。文字列型。リソース型。オブジェクト型のうち、GMP のインスタンス。
     * @param mixed $value
     * @return bool
     */
    public static function isIntegerCastable($value)
    {
        return is_scalar($value) || is_resource($value) || $value instanceof \GMP;
    }
    
    /**
     * 与えられた値を整数型に変換して返します。
     * @link https://triple-underscore.github.io/WebIDL-ja.html#es-integer-types Web IDL （第２版 — 日本語訳）
     * @param bool|int|float|string|resource|\GMP $value
     * @param string $type byte、octet、short、unsigned short、long、unsigned long、long long、unsigned long long
     * @param int|float $min 浮動小数点型で正確に扱える整数の範囲よりも、整数型で扱える整数の範囲が狭ければ (整数型が32bitである環境なら) 浮動小数点数。
     * @param int|float $max 浮動小数点型で正確に扱える整数の範囲よりも、整数型で扱える整数の範囲が狭ければ (整数型が32bitである環境なら) 浮動小数点数。
     * @param int $bits
     * @param booelan $signed
     * @param string $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int|float 整数型の範囲を超える場合は浮動小数点数。
     * @throws \InvalidArgumentException 配列、NULL が与えられた場合。または、GMP 以外のオブジェクトが与えられた場合。
     * @throws \DomainException $extendedAttribute が [EnforceRange]、かつ与えられたの値が $min 〜 $max に収まらなかった場合。
     */
    private static function toInteger($value, $type, $min, $max, $bits, $signed, $extendedAttribute = null)
    {
        /** @var string 要求される型。 */
        $expectedType = sprintf(
            '%s (an integer in the range of %s to %s)',
            $type,
            is_float($min) ? number_format($min, 0, '', '') : $min,
            is_float($max) ? number_format($max, 0, '', '') : $max
        );
        
        if (!self::isIntegerCastable($value)) {
            throw new \InvalidArgumentException(ErrorMessageCreator::create($value, $expectedType));
        }
        
        if ($value instanceof \GMP || is_resource($value) && get_resource_type($value) === 'GMP integer') {
            // GMP数であれば、あらかじめ文字列に変換しておく
            $value = gmp_strval($value);
        }
        
        /** @var int|float 与えられた値の数値表現。整数型の範囲を超える場合は浮動小数点数。整数値となる場合、小数部があれば0方向へ丸められる。 */
        $number = is_float($value) || (float)$value < self::$phpIntMin || (float)$value > PHP_INT_MAX
            ? (float)$value
            : (int)$value;
    
        if ($extendedAttribute === '[EnforceRange]') {
            /** @var int|float 与えられた値の整数表現。整数型の範囲を超える場合は浮動小数点数。 */
            $integer = self::roundTowardZero($number);
            if (!is_finite($number) || $integer < $min || $integer > $max) {
                throw new \DomainException(ErrorMessageCreator::create($value, $expectedType));
            }
        } elseif (!is_nan($number) && $extendedAttribute === '[Clamp]') {
            $number = min($max, max($number, $min));
            $integer = is_float($number) ? round($number, 0, PHP_ROUND_HALF_EVEN) : $number;
        } elseif (!is_finite($number)) {
            $integer = 0;
        } else {
            $integer = self::modulo(self::roundTowardZero($number), pow(2, $bits));
            if ($signed && $integer >= pow(2, $bits - 1)
                && !(PHP_INT_SIZE === 8 && $bits === 64 && $integer <= PHP_INT_MAX)) {
                $integer -= pow(2, $bits);
            }
        }
        
        return is_float($integer) && $integer >= self::$phpIntMin && $integer <= PHP_INT_MAX
            ? (int)$integer
            : $integer;
    }
    
    /**
     * 与えられた数値を0の方向に丸めた整数を返します。
     * @param float|int $value
     * @return float|int
     */
    private static function roundTowardZero($value)
    {
        return is_float($value) ? ($value >= 0 ? 1 : -1) * floor(abs($value)) : $value;
    }
    
    /**
     * 正の剰余を返します。
     * @link https://www.ecma-international.org/ecma-262/7.0/index.html#sec-algorithm-conventions ECMAScript 2015 Language Specification – ECMA-262 6th Edition
     * @param int|float $x
     * @param int|float $y
     * @return int|float
     */
    private static function modulo($x, $y)
    {
        return ($x < $y ? $x : (is_int($y) ? $x % $y : fmod($x, $y))) + ($x < 0 ? $y : 0);
    }
    
    /**
     * 与えられた値を −128〜127 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-byte WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int
     */
    public static function toByte($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'byte', -128, 127, 8, true, $extendedAttribute);
    }
    
    /**
     * 与えられた値を 0〜255 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-octet WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int
     */
    public static function toOctet($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'octet', 0, 255, 8, false, $extendedAttribute);
    }
    
    /**
     * 与えられた値を −32768〜32767 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-short WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int
     */
    public static function toShort($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'short', -32768, 32767, 16, true, $extendedAttribute);
    }
    
    /**
     * 与えられた値を 0〜65535 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-unsigned-short WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int
     */
    public static function toUnsignedShort($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'unsigned short', 0, 65535, 16, false, $extendedAttribute);
    }
    
    /**
     * 与えられた値を −2147483648〜2147483647 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-long WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int
     */
    public static function toLong($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'long', -2147483648, 2147483647, 32, true, $extendedAttribute);
    }
    
    /**
     * 与えられた値を 0〜4294967295 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-unsigned-long WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int|float 32bit版のPHP、またはWindows版のPHP 5.6以前において、整数型の範囲を超える場合は浮動小数点数。
     */
    public static function toUnsignedLong($value, $extendedAttribute = null)
    {
        return self::toInteger($value, 'unsigned long', 0, 4294967295, 32, false, $extendedAttribute);
    }
    
    /**
     * 与えられた値を −9223372036854775808〜9223372036854775807 の範囲の整数に変換して返します。
     * 32bit版のPHP、またはWindows版のPHP 5.6以前では、−9007199254740991〜9007199254740991 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-long WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int|float 32bit版のPHP、またはWindows版のPHP 5.6以前において、整数型の範囲を超える場合は浮動小数点数。
     */
    public static function toLongLong($value, $extendedAttribute = null)
    {
        $longLongMin = self::$phpIntMin > -9007199254740991 ? -9007199254740991 : ~9223372036854775807;
        $longLongMax = PHP_INT_MAX < 9007199254740991 ? 9007199254740991 : 9223372036854775807;
        return self::toInteger($value, 'long long', $longLongMin, $longLongMax, 64, true, $extendedAttribute);
    }
    
    /**
     * 与えられた値を 0〜9223372036854775807 の範囲の整数に変換して返します。
     * 32bit版のPHP、またはWindows版のPHP 5.6以前では、0〜9007199254740991 の範囲の整数に変換して返します。
     * @link https://www.w3.org/TR/WebIDL-1/#idl-unsigned-long-long WebIDL Level 1
     * @param bool|int|float|string|resource|\GMP $value
     * @param string|null $extendedAttribute 拡張属性。[EnforceRange] か [Clamp] のいずれか。
     * @return int|float 32bit版のPHP、またはWindows版のPHP 5.6以前において、整数型の範囲を超える場合は浮動小数点数。
     */
    public static function toUnsignedLongLong($value, $extendedAttribute = null)
    {
        if (PHP_INT_MAX < 9007199254740991) {
            $unsignedLongLongMax = 9007199254740991;
        } elseif (PHP_INT_MAX < 18446744073709551615) {
            $unsignedLongLongMax = PHP_INT_MAX;
        } else {
            $unsignedLongLongMax = 18446744073709551615;
        }
        return self::toInteger($value, 'unsigned long long', 0, $unsignedLongLongMax, 64, false, $extendedAttribute);
    }
}
