<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;

/**
 * Moteur de formules sécurisé (aucun eval()).
 *
 * Grammaire :
 *   expr    := or
 *   or      := and ( '||' and )*
 *   and     := cmp ( '&&' cmp )*
 *   cmp     := add ( ('<'|'<='|'>'|'>='|'=='|'!=') add )?
 *   add     := mul ( ('+'|'-') mul )*
 *   mul     := unary ( ('*'|'/'|'%') unary )*
 *   unary   := ('-'|'+'|'!') unary | power
 *   power   := primary ( '^' unary )?
 *   primary := nombre | variable | fonction '(' args ')' | '(' expr ')'
 *
 * Une valeur manquante (null) ou une division par zéro rend le résultat
 * "non calculable" (null) au lieu de produire une erreur.
 */
final class FormulaEngine
{
    public const FUNCTIONS = [
        'div'      => 'div(a, b) : division sûre (null si b = 0)',
        'pct'      => 'pct(a, b) : a / b × 100 (null si b = 0)',
        'min'      => 'min(a, b, ...) : minimum',
        'max'      => 'max(a, b, ...) : maximum',
        'moy'      => 'moy(a, b, ...) : moyenne',
        'round'    => 'round(x, n) : arrondi à n décimales',
        'floor'    => 'floor(x) : partie entière inférieure',
        'ceil'     => 'ceil(x) : partie entière supérieure',
        'abs'      => 'abs(x) : valeur absolue',
        'sqrt'     => 'sqrt(x) : racine carrée',
        'log'      => 'log(x) : logarithme népérien',
        'si'       => 'si(condition, alors, sinon) : condition (alias if)',
        'if'       => 'if(condition, alors, sinon)',
        'coalesce' => 'coalesce(a, b, ...) : première valeur non nulle',
    ];

    private array $tokens = [];
    private int $pos = 0;

    /** Analyse une formule en arbre syntaxique (résultat mis en cache APCu). */
    public static function parse(string $formula): array
    {
        $formula = trim($formula);
        return Cache::remember('ast:' . sha1($formula), function () use ($formula) {
            $p = new self();
            $p->tokens = self::tokenize($formula);
            $p->pos = 0;
            if (!$p->tokens) {
                throw new FormulaException('La formule est vide.');
            }
            $ast = $p->parseOr();
            if ($p->pos < count($p->tokens)) {
                $t = $p->tokens[$p->pos];
                throw new FormulaException("Symbole inattendu « {$t[1]} » en position {$t[2]}.");
            }
            return $ast;
        }, 86400, 'formules');
    }

    /** Vérifie la syntaxe et les variables autorisées. Retourne null si OK, sinon le message d'erreur. */
    public static function validate(string $formula, array $allowedVars): ?string
    {
        try {
            $ast = self::parse($formula);
        } catch (FormulaException $e) {
            return $e->getMessage();
        }
        $unknown = array_diff(self::variables($ast), $allowedVars);
        if ($unknown) {
            return 'Variable(s) inconnue(s) : ' . implode(', ', $unknown) . '.';
        }
        return null;
    }

    /** Liste des variables utilisées dans l'arbre. */
    public static function variables(array $ast): array
    {
        $out = [];
        $walk = function (array $n) use (&$walk, &$out) {
            switch ($n['t']) {
                case 'var': $out[$n['n']] = true; break;
                case 'un':  $walk($n['a']); break;
                case 'bin': $walk($n['a']); $walk($n['b']); break;
                case 'fn':  foreach ($n['args'] as $a) { $walk($a); } break;
            }
        };
        $walk($ast);
        return array_keys($out);
    }

    public static function evaluate(string|array $formula, array $vars): ?float
    {
        $ast = is_array($formula) ? $formula : self::parse($formula);
        $v = self::evalNode($ast, $vars);
        if ($v === null || is_nan($v) || is_infinite($v)) {
            return null;
        }
        return $v;
    }

    // ------------------------------------------------------------------
    private static function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if (ctype_space($c)) { $i++; continue; }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                if (!preg_match('/\G\d*\.?\d+(?:[eE][+-]?\d+)?|\G\d+\.?/', $s, $m, 0, $i)) {
                    throw new FormulaException("Nombre invalide en position $i.");
                }
                $tokens[] = ['num', $m[0], $i];
                $i += strlen($m[0]);
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $i);
                $tokens[] = ['id', $m[0], $i];
                $i += strlen($m[0]);
                continue;
            }
            $two = substr($s, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['op', $two, $i];
                $i += 2;
                continue;
            }
            if (strpos('+-*/%^()<>!,', $c) !== false) {
                $tokens[] = ['op', $c, $i];
                $i++;
                continue;
            }
            throw new FormulaException("Caractère non autorisé « $c » en position $i.");
        }
        return $tokens;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function accept(string ...$ops): ?string
    {
        $t = $this->peek();
        if ($t && $t[0] === 'op' && in_array($t[1], $ops, true)) {
            $this->pos++;
            return $t[1];
        }
        return null;
    }

    private function expect(string $op): void
    {
        if ($this->accept($op) === null) {
            $t = $this->peek();
            $where = $t ? "en position {$t[2]}" : 'en fin de formule';
            throw new FormulaException("« $op » attendu $where.");
        }
    }

    private function parseOr(): array
    {
        $n = $this->parseAnd();
        while ($this->accept('||')) {
            $n = ['t' => 'bin', 'op' => '||', 'a' => $n, 'b' => $this->parseAnd()];
        }
        return $n;
    }

    private function parseAnd(): array
    {
        $n = $this->parseCmp();
        while ($this->accept('&&')) {
            $n = ['t' => 'bin', 'op' => '&&', 'a' => $n, 'b' => $this->parseCmp()];
        }
        return $n;
    }

    private function parseCmp(): array
    {
        $n = $this->parseAdd();
        if ($op = $this->accept('<', '<=', '>', '>=', '==', '!=')) {
            $n = ['t' => 'bin', 'op' => $op, 'a' => $n, 'b' => $this->parseAdd()];
        }
        return $n;
    }

    private function parseAdd(): array
    {
        $n = $this->parseMul();
        while ($op = $this->accept('+', '-')) {
            $n = ['t' => 'bin', 'op' => $op, 'a' => $n, 'b' => $this->parseMul()];
        }
        return $n;
    }

    private function parseMul(): array
    {
        $n = $this->parseUnary();
        while ($op = $this->accept('*', '/', '%')) {
            $n = ['t' => 'bin', 'op' => $op, 'a' => $n, 'b' => $this->parseUnary()];
        }
        return $n;
    }

    private function parseUnary(): array
    {
        if ($op = $this->accept('-', '+', '!')) {
            return ['t' => 'un', 'op' => $op, 'a' => $this->parseUnary()];
        }
        return $this->parsePower();
    }

    private function parsePower(): array
    {
        $n = $this->parsePrimary();
        if ($this->accept('^')) {
            $n = ['t' => 'bin', 'op' => '^', 'a' => $n, 'b' => $this->parseUnary()];
        }
        return $n;
    }

    private function parsePrimary(): array
    {
        $t = $this->peek();
        if ($t === null) {
            throw new FormulaException('Formule incomplète.');
        }
        if ($t[0] === 'num') {
            $this->pos++;
            return ['t' => 'num', 'v' => (float) $t[1]];
        }
        if ($t[0] === 'id') {
            $this->pos++;
            $name = $t[1];
            if ($this->accept('(')) {
                $fn = strtolower($name);
                if (!isset(self::FUNCTIONS[$fn])) {
                    throw new FormulaException("Fonction inconnue « $name » en position {$t[2]}.");
                }
                $args = [];
                if (!$this->accept(')')) {
                    do {
                        $args[] = $this->parseOr();
                    } while ($this->accept(','));
                    $this->expect(')');
                }
                self::checkArity($fn, count($args), $t[2]);
                return ['t' => 'fn', 'n' => $fn, 'args' => $args];
            }
            return ['t' => 'var', 'n' => $name];
        }
        if ($this->accept('(')) {
            $n = $this->parseOr();
            $this->expect(')');
            return $n;
        }
        throw new FormulaException("Symbole inattendu « {$t[1]} » en position {$t[2]}.");
    }

    private static function checkArity(string $fn, int $n, int $pos): void
    {
        $rules = [
            'div' => [2, 2], 'pct' => [2, 2], 'round' => [1, 2], 'floor' => [1, 1], 'ceil' => [1, 1],
            'abs' => [1, 1], 'sqrt' => [1, 1], 'log' => [1, 1], 'si' => [3, 3], 'if' => [3, 3],
            'min' => [1, 50], 'max' => [1, 50], 'moy' => [1, 50], 'coalesce' => [1, 50],
        ];
        [$lo, $hi] = $rules[$fn];
        if ($n < $lo || $n > $hi) {
            throw new FormulaException("Nombre d'arguments invalide pour $fn() en position $pos.");
        }
    }

    private static function evalNode(array $n, array $vars): ?float
    {
        switch ($n['t']) {
            case 'num':
                return $n['v'];
            case 'var':
                if (!array_key_exists($n['n'], $vars)) {
                    throw new FormulaException("Variable inconnue « {$n['n']} ».");
                }
                $v = $vars[$n['n']];
                return $v === null || $v === '' ? null : (float) $v;
            case 'un':
                $a = self::evalNode($n['a'], $vars);
                if ($n['op'] === '!') {
                    return $a ? 0.0 : 1.0;
                }
                return $a === null ? null : ($n['op'] === '-' ? -$a : $a);
            case 'bin':
                $op = $n['op'];
                if ($op === '&&' || $op === '||') {
                    $a = (bool) self::evalNode($n['a'], $vars);
                    if ($op === '&&' && !$a) return 0.0;
                    if ($op === '||' && $a) return 1.0;
                    return self::evalNode($n['b'], $vars) ? 1.0 : 0.0;
                }
                $a = self::evalNode($n['a'], $vars);
                $b = self::evalNode($n['b'], $vars);
                if ($a === null || $b === null) {
                    return null;
                }
                return match ($op) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? null : $a / $b,
                    '%' => $b == 0.0 ? null : fmod($a, $b),
                    '^' => $a ** $b,
                    '<' => (float) ($a < $b),
                    '<=' => (float) ($a <= $b),
                    '>' => (float) ($a > $b),
                    '>=' => (float) ($a >= $b),
                    '==' => (float) (abs($a - $b) < 1e-9),
                    '!=' => (float) (abs($a - $b) >= 1e-9),
                };
            case 'fn':
                return self::evalFn($n['n'], $n['args'], $vars);
        }
        return null;
    }

    private static function evalFn(string $fn, array $args, array $vars): ?float
    {
        if ($fn === 'si' || $fn === 'if') {
            $c = self::evalNode($args[0], $vars);
            return self::evalNode($c ? $args[1] : $args[2], $vars);
        }
        if ($fn === 'coalesce') {
            foreach ($args as $a) {
                $v = self::evalNode($a, $vars);
                if ($v !== null) return $v;
            }
            return null;
        }
        $vals = array_map(fn($a) => self::evalNode($a, $vars), $args);
        if (in_array($fn, ['min', 'max', 'moy'], true)) {
            $vals = array_values(array_filter($vals, fn($v) => $v !== null));
            if (!$vals) return null;
            return match ($fn) {
                'min' => min($vals),
                'max' => max($vals),
                'moy' => array_sum($vals) / count($vals),
            };
        }
        if (in_array(null, $vals, true)) {
            return null;
        }
        return match ($fn) {
            'div'   => $vals[1] == 0.0 ? null : $vals[0] / $vals[1],
            'pct'   => $vals[1] == 0.0 ? null : $vals[0] / $vals[1] * 100,
            'round' => round($vals[0], (int) ($vals[1] ?? 0)),
            'floor' => floor($vals[0]),
            'ceil'  => ceil($vals[0]),
            'abs'   => abs($vals[0]),
            'sqrt'  => $vals[0] < 0 ? null : sqrt($vals[0]),
            'log'   => $vals[0] <= 0 ? null : log($vals[0]),
        };
    }
}
