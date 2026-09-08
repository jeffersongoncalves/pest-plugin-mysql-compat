# Pacote público: compatibilidade MySQL → SQLite para testes

Especificação para criar um pacote que faça uma suíte de testes rodar em SQLite
sem alterar uma linha do SQL escrito para MySQL.

**Status:** proposta. Todos os números e comportamentos abaixo foram medidos, não
inferidos — o ambiente da medição está no fim do documento.

---

## 1. O problema

Projeto Laravel com banco legado MySQL. A suíte roda em SQLite em memória para ser
rápida e não depender de um MySQL de desenvolvimento. Toda query que usa função do
MySQL morre no teste, e as saídas usuais são ruins:

- reescrever a query para o subconjunto que o SQLite entende → o teste passa a
  cobrir um SQL que não é o de produção;
- marcar o teste como `->todo()` → a regra de negócio fica sem cobertura;
- exigir MySQL para a suíte → perde-se o isolamento e a velocidade.

O pacote existe para eliminar essas três saídas. **A query de produção é a verdade;
quem se adapta é o banco de teste.**

### 1.1 As quatro classes de problema

Não é um problema só. São quatro, e cada uma pede um mecanismo diferente — é isso
que justifica um pacote em vez de uma lista de `sqliteCreateFunction`.

| # | Classe | Sintoma | Mecanismo necessário |
|---|--------|---------|----------------------|
| 1 | Função ausente | `no such function: DATE_FORMAT` | UDF |
| 2 | Função nativa com semântica divergente | passa, resultado errado, silencioso | UDF que **sobrescreve** a nativa |
| 3 | Sintaxe não registrável | `near "ISNULL": syntax error` | reescrita do SQL |
| 4 | Afinidade de tipo do parâmetro | `WHERE x >= ?` sempre falso com float | reescrita do binding |

A classe 2 é a mais perigosa: o teste fica verde com o número errado.
A classe 3 não é alcançável por UDF — o parser do SQLite nem chega na função.

---

## 2. Escopo

**Entrega:**

1. Catálogo de UDFs que replicam funções do MySQL, com fidelidade de semântica
   (incluindo propagação de `NULL` e contagem em bytes).
2. Reescritor de SQL para o que é sintaxe, não função.
3. Reescritor de binding de float.
4. Integração de uma linha para Pest/PHPUnit, e adaptador para Laravel.
5. Um teste por função, provando a paridade com o MySQL.

**Fora do escopo (não-objetivos explícitos):**

- Rodar produção em SQLite. É um pacote de `require-dev`.
- Emular o MySQL por completo. O catálogo cresce por demanda medida, não por
  varredura do manual.
- Traduzir DDL, tipos de coluna ou índices. Isso é migração de schema, outro
  problema.
- Esconder divergência sem avisar. Onde a paridade for impossível, o pacote
  **falha alto** com mensagem que aponta o caminho (ver §7).

---

## 3. Catálogo de funções

Levantamento feito num ERP Laravel real (~2 900 testes de feature, ~1 750 de
unidade), varrendo os literais de string via `token_get_all` — só string, nunca
código PHP, para não confundir `round()` do PHP com `ROUND()` do SQL. Arquivos
`.blade.php` ficaram fora: o tokenizer os lê como HTML e gera falso positivo.

**30 funções distintas em uso.** A coluna "usos" indica prioridade de
implementação, não urgência de correção.

### 3.1 Ausentes no SQLite — precisam de UDF

| Função | Usos | Assinatura | Observação de fidelidade |
|--------|-----:|------------|--------------------------|
| `IF` | 72 | 3 | SQLite tem `IIF` nativo; registrar `IF` funciona |
| `JSON_UNQUOTE` | 29 | 1 | ver §5.3 — não é identidade |
| `CONCAT` | 19 | variádica | **propaga `NULL`** (ver §5.1) |
| `SUBSTRING` | 15 | 2–3 | 1-indexado como o MySQL |
| `DATE_FORMAT` | 10 | 2 | ver §5.4 (`0000-00-00`) |
| `LPAD` | 10 | 3 | |
| `LEFT` | 7 | 2 | |
| `RIGHT` | 2 | 2 | |
| `SUBSTRING_INDEX` | 2 | 3 | contagem negativa conta do fim |
| `PERIOD_ADD` | 2 | 2 | devolve o mesmo tipo que recebeu |
| `LAST_DAY` | 1 | 1 | |
| `NOW` | 1 | 0 | |

### 3.2 Nativas e equivalentes — não tocar

`ABS`, `CAST`, `COALESCE`, `COUNT`, `MAX`, `MIN`, `SUM`, `NULLIF`, `REPLACE`,
`TRIM`, `UPPER`, `LOWER`, `SUBSTR`.

`SUBSTR` com início negativo confere (`SUBSTR('abcdef', -3)` → `'def'` nos dois).

### 3.3 Nativas com semântica divergente — precisam sobrescrever

| Função | Usos | SQLite | MySQL | Ação |
|--------|-----:|--------|-------|------|
| `LENGTH` | 12 | `LENGTH('ção')` → **3** (caracteres) | → **5** (bytes) | sobrescrever com `strlen` |
| `ROUND` | 6 | `ROUND(1234.5, -2)` → **1235.0** | → **1200** | sobrescrever para casa negativa |
| `JSON_EXTRACT` | 29 | devolve escalar cru: `'x'`, `7` | devolve com aspas JSON: `"x"` | ver §5.3 |

`LENGTH` é a divergência mais traiçoeira: em coluna de dígitos (CPF, CNPJ, CEP) os
dois concordam e o teste passa; em texto acentuado divergem em silêncio.

### 3.4 Fora da lista, mas do catálogo desejável

Aparecem no MySQL e não neste projeto. Implementar sob demanda, com o mesmo rigor
de teste: `IFNULL`, `CONCAT_WS`, `RPAD`, `DATEDIFF`, `DAY`, `MONTH`, `YEAR`,
`DAYOFMONTH`, `ADDDATE`, `PERIOD_DIFF`, `STR_TO_DATE`, `TIMESTAMPDIFF`,
`GROUP_CONCAT` (existe no SQLite, separador difere), `FIND_IN_SET`, `FIELD`,
`GREATEST`, `LEAST`.

---

## 4. Como o pacote instala as UDFs

### 4.1 A API do PDO

Medido no PHP 8.4.25:

- `PDO::sqliteCreateFunction()` funciona e **não emitiu notice de depreciação**
  (testado com `error_reporting(E_ALL)` e handler próprio).
- `Pdo\Sqlite::createFunction()` existe, porém só numa instância criada por
  `PDO::connect()`. O Laravel instancia `new PDO(...)`, então o objeto é `PDO`
  puro e **só o método legado está disponível**.

**Decisão:** usar `sqliteCreateFunction()`, com detecção do método novo para o dia
em que o legado sair. Não inverter a ordem: quem chama pelo Laravel nunca terá o
método novo.

### 4.2 O ponto de instalação

O PDO do Laravel é resolvido de forma preguiçosa, então instalar as funções na
construção da conexão força a abertura do PDO antes da hora. O gancho certo é o
`getPdo()`, com registro idempotente por instância de PDO:

```php
final class CompatSqliteConnection extends \Illuminate\Database\SQLiteConnection
{
    /** @var \WeakMap<\PDO, true>|null */
    private static ?\WeakMap $prepared = null;

    public function getPdo(): \PDO
    {
        $pdo = parent::getPdo();

        self::$prepared ??= new \WeakMap;

        if (! isset(self::$prepared[$pdo])) {
            self::$prepared[$pdo] = true;

            FunctionRegistry::install($pdo);
        }

        return $pdo;
    }
}
```

`WeakMap` em vez de array de `spl_object_id`: a conexão morre no fim do teste e o
registro tem de morrer com ela, sem vazar entre testes.

Registrar a conexão como resolvedora do driver, uma vez, no `setUp`:

```php
\Illuminate\Database\Connection::resolverFor(
    'sqlite',
    fn ($pdo, $database, $prefix, $config) => new CompatSqliteConnection($pdo, $database, $prefix, $config),
);
```

Isso cobre de uma vez: a conexão compartilhada de um harness de schema legado, as
conexões que testes antigos montam à mão com `config([...])`, e o banco default da
aplicação — sem que nenhum deles precise saber que o pacote existe.

---

## 5. Fidelidade: os casos que exigem cuidado

### 5.1 `CONCAT` e `NULL`

MySQL devolve `NULL` se qualquer argumento é `NULL`. A implementação ingênua faz
`(string) $part` e devolve `''` — divergência silenciosa que troca "sem dado" por
"texto vazio", e some com a linha num `WHERE ... IS NULL`.

Medido que a fidelidade é possível:

```
CONCAT('a', NULL) => NULL
CONCAT('a', 'b')  => 'ab'
```

O pacote deve propagar `NULL`. Se algum consumidor depender do comportamento
permissivo, isso é uma opção explícita, nunca o default.

### 5.2 `LENGTH` em bytes

```php
$pdo->sqliteCreateFunction('LENGTH', fn (?string $v): int => strlen((string) $v), 1);
```

Medido: `LENGTH('ção')` → `5`, batendo com o MySQL. A nativa do SQLite devolvia
`3`. Quem precisa de caracteres no MySQL usa `CHAR_LENGTH`, então a sobrescrita não
tira nada de ninguém.

### 5.3 `JSON_EXTRACT` + `JSON_UNQUOTE`

O par é idiomático no MySQL porque `JSON_EXTRACT` devolve JSON, com aspas. No
SQLite `json_extract` já devolve o escalar cru:

```
JSON_EXTRACT('{"a":"x"}', '$.a')  => 'x'   (SQLite)   vs   "x"  (MySQL)
JSON_EXTRACT('{"a":7}',   '$.a')  => 7     (integer, nos dois)
```

Consequência: `JSON_UNQUOTE` **não pode ser identidade cega**. A implementação
segura tira as aspas duplas envolventes só quando existem, e devolve o valor
intocado quando não — assim funciona tanto sobre a nativa do SQLite quanto sobre
uma `JSON_EXTRACT` que o pacote venha a sobrescrever.

Decidir e documentar: sobrescrever `JSON_EXTRACT` para devolver JSON (fiel ao
MySQL, mas quebra quem já escreveu para o SQLite), ou manter a nativa e adaptar o
`JSON_UNQUOTE`. Recomendação: manter a nativa, adaptar o `JSON_UNQUOTE`, e cobrir
os dois formatos no teste.

### 5.4 Datas zeradas do legado

Banco legado MySQL guarda `'0000-00-00'` como "sem data". Passar isso por
`strtotime()` devolve 30/11/1999 — uma data plausível, num teste verde, com o
número errado. Toda UDF de data do pacote deve tratar `''`, `null` e prefixo
`0000-00-00` como `NULL`.

---

## 6. O que não é função: reescrita de SQL

Aqui está o valor que uma lista de UDFs não entrega. Registrei a UDF e testei: o
parser do SQLite **não chega nela**, porque o problema é sintático.

### 6.1 `ISNULL`

```
ISNULL(NULL)  com UDF registrada  => ERRO: near "ISNULL": syntax error
NULL ISNULL                       => 1
```

O SQLite trata `ISNULL` como operador postfix, não como nome de função. Registrar
não adianta. **Reescrita:** `ISNULL(<expr>)` → `((<expr>) IS NULL)`.

Exige um parser que case parênteses balanceados — `ISNULL(IF(a, b, c))` tem de
funcionar —, e que ignore ocorrências dentro de string, identificador entre aspas e
comentário.

### 6.2 `DATE_ADD` / `DATE_SUB` com `INTERVAL`

```
DATE_ADD('2026-05-01', INTERVAL 3 DAY)   com UDF registrada  => ERRO: near "3": syntax error
DATE_ADD('2026-05-01', '3 DAY')          com UDF registrada  => a UDF é chamada
```

`INTERVAL n UNIT` é sintaxe do MySQL. A UDF só é alcançada quando a chamada **não**
usa `INTERVAL`. **Reescrita:** `DATE_ADD(x, INTERVAL <expr> <UNIT>)` →
`DATE_ADD_COMPAT(x, (<expr>), '<UNIT>')`, com a UDF de três argumentos fazendo a
aritmética. O `<expr>` pode ser uma expressão de coluna
(`INTERVAL (t2.i * 10 + t1.i) DAY`), então não serve regex de número.

### 6.3 Onde interceptar

O reescritor precisa agir **antes do `prepare()`**. Em Laravel, `Connection::run()`
é o funil de todos os caminhos de execução — mas ver §7 antes de escolher esse
ponto.

---

## 7. Classe 4: o binding de float

Problema irmão, mesma raiz (o banco de teste divergindo do de produção), e nenhuma
função resolve.

Medido em PDO puro, fora do Laravel, nos três tipos de parâmetro:

```
bindValue(0.005) sem tipo   => typeof = text     1000.0 >= 0.005  =>  falso
bindValue(0.005, PARAM_STR) => typeof = text     1000.0 >= 0.005  =>  falso
bindValue(0.005, PARAM_INT) => typeof = integer  (trunca para 0)
ATTR_EMULATE_PREPARES       => ignorado pelo driver sqlite
```

O PDO SQLite não tem como ligar um float: `PARAM_STR` — que é o default do Laravel
para tudo que não é int/bool — grava **TEXT**, e no SQLite **todo número ordena
antes de todo texto**. Logo `WHERE ABS(x) >= ?` com tolerância `0.005` é sempre
falso. O MySQL coage e passa. Custo real medido: **10 testes** de um relatório de
divergência de saldo, todos com a query correta.

**Solução:** trocar o `?` do binding float por literal numérico no SQL antes do
`prepare()`, e só o dele — os outros parâmetros continuam ligados.

Requisitos do reescritor, todos vindos de erro cometido:

- **Ignorar `?` que não é placeholder:** dentro de string, de identificador entre
  aspas, e de comentário de linha e de bloco. Respeitar aspa escapada por
  duplicação (`'D''Ávila?'`).
- **Parentizar o literal.** `Valor - ?` com `-0.5` viraria `Valor - -0.5`, e `--`
  abre comentário de linha em SQL. `(-0.5)` resolve.
- **Precisão de ida e volta.** `json_encode` de float preserva o valor
  (`serialize_precision = -1`); `(string)` não garante.
- **Recusar em vez de adivinhar.** Contagem de `?` livres diferente da de
  bindings, binding nomeado, ou float não finito (`INF`, `NAN`) → devolver o par
  original intacto. Errar o SQL de um teste em silêncio é pior que a falha original.
- **Só na leitura.** Aqui está a lição que custou uma iteração: a primeira versão
  interceptava `Connection::run()`, que é o funil de tudo, e quebrou um teste que
  confere o arredondamento gravado **lendo o query log do `INSERT`** — com o float
  saindo da lista de bindings, o log perdeu o valor. Na escrita o SQLite converte
  pela afinidade da coluna e não precisa de reescrita. Interceptar `select()` e
  `cursor()`: fecha o problema e mantém o caminho de escrita byte a byte igual.

---

## 8. Arquitetura

```
src/
  Compat.php                     # fachada: Compat::install(), Compat::functions()
  FunctionRegistry.php           # instala as UDFs num PDO, idempotente
  Functions/
    StringFunctions.php          # CONCAT, LPAD, SUBSTRING, LEFT, ...
    DateFunctions.php            # DATE_FORMAT, LAST_DAY, NOW, ...
    PeriodFunctions.php          # PERIOD_ADD, PERIOD_DIFF
    JsonFunctions.php            # JSON_UNQUOTE
    NumericFunctions.php         # ROUND (sobrescrita)
  Rewriters/
    SqlRewriter.php              # orquestra os rewriters
    IsNullRewriter.php           # ISNULL(x) -> (x IS NULL)
    IntervalRewriter.php         # DATE_ADD(x, INTERVAL e U) -> UDF de 3 args
    FloatBindingRewriter.php     # ? de float -> literal
    Lexer.php                    # varredura que ignora string/aspas/comentário
  Laravel/
    CompatSqliteConnection.php   # getPdo() + select()/cursor()
    CompatServiceProvider.php    # opcional, para uso fora de teste
  Pest/
    Plugin.php                   # expõe a ativação no Pest
```

O `Lexer` é compartilhado pelos três rewriters. Ele é o componente crítico: um bug
ali corrompe SQL de teste em silêncio. Merece o teste mais denso do pacote.

---

## 9. API pública

Objetivo: uma linha para o caso comum, e granularidade para quem precisa.

### Pest

```php
// tests/Pest.php
uses(MysqlCompat::class)->in('Feature', 'Unit');
```

ou, sem trait, na configuração global:

```php
pest()->beforeEach(fn () => MysqlCompat::install());
```

### PHPUnit / Laravel puro

```php
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        MysqlCompat::install();

        parent::setUp();
    }
}
```

### Configuração granular

```php
MysqlCompat::install(
    functions: ['DATE_FORMAT', 'IF', 'CONCAT'],   // default: todas
    rewriters: MysqlCompat::REWRITE_ALL,          // ou REWRITE_NONE, ou lista
    strict: true,                                 // ver abaixo
);
```

`strict: true` é a opção que diferencia o pacote de um monte de UDFs: ao encontrar
uma função do MySQL **sem** implementação fiel, falha o teste com o nome da função
e o arquivo, em vez de deixar o SQLite decidir. Um teste verde por acidente é o
inimigo.

### Diagnóstico

Comando que roda o levantamento do §3 no projeto do consumidor, para ele saber o
que precisa antes de precisar:

```
vendor/bin/mysql-compat scan --path=app --path=modules
```

Saída: função, contagem de usos, situação (nativa / coberta / **sem cobertura** /
**exige reescrita**), e arquivo:linha de exemplo. É o mesmo relatório que originou
este documento.

---

## 10. Teste do pacote

Regra: **nenhuma função entra sem um teste que prove a paridade.** Duas camadas.

1. **Unidade, contra o valor esperado do MySQL.** Tabela de casos por função,
   incluindo obrigatoriamente: `NULL` em cada posição de argumento, string vazia,
   acento, e o caso de borda do legado (`'0000-00-00'` para data, `''` para
   período).

2. **Paridade real, opcional em CI.** Suíte que roda a mesma expressão nos dois
   bancos e compara. Roda contra um MySQL de serviço no CI, `skip` sem ele. É a
   única defesa contra o shim que "parece certo": foi assim que apareceu a
   divergência de `LENGTH`.

```php
it('replica o MySQL', function (string $expression, string $expected): void {
    expect(sqliteScalar($expression))->toBe($expected);
})->with([
    ["CONCAT('a', NULL)", null],
    ["LENGTH('ção')", 5],
    ["DATE_FORMAT('0000-00-00', '%d/%m/%Y')", null],
    ["ROUND(1234.5, -2)", 1200.0],
]);
```

Para os rewriters, teste no nível do SQL (entra string, sai string) além do teste
de integração — a asserção sobre a string é a que pega o `?` dentro de comentário.

---

## 11. Limites conhecidos

Documentar na primeira versão do README, não descobrir depois:

- **`WHERE` de `UPDATE`/`DELETE` com float** não é reescrito (só `select`/`cursor`,
  §7). Nenhum caso real encontrado; documentar em vez de cobrir por especulação.
- **Reescrita é textual.** SQL montado por concatenação em tempo de execução com
  fragmento vindo de dado é território de risco; o lexer recusa quando não tem
  certeza, e recusar significa o erro original de volta, não corrupção.
- **`GROUP_CONCAT`** existe nos dois com separador default diferente (`,` nos dois,
  mas ordenação e `DISTINCT` divergem). Entra no catálogo com teste de paridade,
  não como "já funciona".
- **Collation e ordenação de texto** não são cobertos. `ORDER BY` de texto
  acentuado difere entre os bancos e isso é problema de collation, não de função.
- **`LENGTH` sobrescrita** muda o comportamento de quem já escrevia esperando a
  nativa do SQLite. É breaking para esse consumidor — merece nota de migração.

---

## 12. Roadmap

**Fase 1 — o que paga o pacote.** `FunctionRegistry` + as 12 funções ausentes do
§3.1 + `LENGTH` e `ROUND` do §3.3. Integração Pest e PHPUnit. Testes de unidade.

**Fase 2 — o diferencial.** `Lexer` + `FloatBindingRewriter` (o de maior retorno
medido: 10 testes) + `IsNullRewriter` + `IntervalRewriter`.

**Fase 3 — confiança.** `scan` de diagnóstico, modo `strict`, suíte de paridade
contra MySQL real no CI.

**Fase 4 — alcance.** Catálogo do §3.4 sob demanda. Adaptador para quem não usa
Laravel (Doctrine, PDO puro).

---

## 13. Nome

Decidido: `jeffersongoncalves/pest-plugin-mysql-compat`.

---

## Ambiente da medição

- SQLite 3.53.4 (via pdo_sqlite), PHP 8.4.25
- Laravel 12, Pest 3
- Projeto de origem: ERP Laravel com banco legado MySQL, ~2 900 testes de feature
  e ~1 750 de unidade
- Levantamento de funções: `token_get_all` sobre `app/`, `modules/`, `database/`,
  `routes/`, `config/` e os pacotes internos, excluindo `.blade.php`
