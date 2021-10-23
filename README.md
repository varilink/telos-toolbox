# Telos UK - Toolbox

David Williamson @ Varilink Computing Ltd

------

A toolbox of CLI tools that have proved useful when working with  [Telos UK](https://telosuk.io/), "a Founding Block Producer on the Telos Public Network". I'm sharing it in case it might prove useful to anybody else in the Telos community, or the wider EOSIO community.

The tools are implemented as Docker Compose services and so you will require that Docker is installed on your client in order to use them as illustrated below. Alternatively you could, if it's within your gift, run them native on your host but of course you would have to recreate the environment setup that is encapsulated in the Docker Compose services.

The tools use PHP that is mapped as volumes into the Docker containers (so that edits can be made an tested in-situ) and the setup includes the facility for XDEBUG integration with a client IDE. So, if you wanted to modify and extend these tools then you should be setup from the get-go for that. Alternatively, just run them as is with only Docker as a dependency.

## "How to Use" by Tool

###  API Report

This tool generates a report on API nodes advertised by the Telos block producers for a chain; for example, to output a report for the Telos testnet to the file api-report.csv:

```bash
docker-compose run --rm api-report https://testnet-api.telosuk.io > api-report.csv
```

The report requires that a valid API endpoint for the chain being queried is supplied; the example above uses https://testnet-api.telosuk.io, which is the Telos UK API endpoint for the Telos testnet. As the example suggests, the output is in CSV format for easy import into a spreadsheet application. It contains a header row and columns as follows:

- Producer = producer's account name
- API Endpoint = advertised API endpoint
- Version = nodeos version string reported by the endpoint
- Block One = whether or not block 1 could be found via the API Endpoint
- Error = any error encountered by the tool (note that some errors mean that the columns "API Endpoint", "Version" and "Block One" won't be populated)

This report uses conventions adopted by the community of Telos Network Block Producers. Those same conventions can be used to identify an API endpoint to be used as the parameter to the report - see [Telos Network Block Producers](#telos-network-block-producers) below. Note that this makes this tool specific to the Telos Network, unless there are other EOSIO based networks that have adopted the same conventions.

### Get Peers

This tool generates two ouputs:

1. A list of `p2p-peer-address` entries for a chain suitable for insertion into the nodeos configuration file for a node that is to be synchronized with that chain. The list also contains .ini file comments containing the timestamp of when it was generated, the chain id and the producer name associated with each `p2p-peer-address` - so, it's self auditing.
2. An error report in CSV format for any block producer on the chain for which an error was encountered when trying to determine that `p2p-peer-address`. This contains a header row and two columns, one for the producer name and the other for a description of the error encountered.

For example, to generate a `peers.ini` file and associated `errors.csv` report for Telos testnet:

```bash
docker-compose run -T --rm get-peers https://testnet-api.telosuk.io 1>peers.ini 2>errors.csv
```

The report requires that a valid API endpoint for the chain being queried is supplied; the example above uses https://testnet-api.telosuk.io, which is the Telos UK API endpoint for the Telos testnet. It writes `p2p-peer-address` entries to STDOUT and reports the issues encountered when trying to find valid P2P peer addresses to STDERR. By default, Docker Compose attaches the local terminal to both STDOUT and STDERR, therefore to separate the `p2p-peer-address` list and the issue reports in the output we must use the `-T` flag as per the above.

Note that the report does *not* attempt nodeos communication with a P2P endpoint as a validation check for the `p2p-peer-address` entries. It's tests include, for example, validation by telnet to the advertised address but they do not include anything based on the relevant EOSIO communication protocols. Thus if an advertised `p2p-peer-address` passes all the tests that this tool applies this is *not* a guarantee that synchronisation via that address will be possible.

Conversely, I'm fairly sure that if an address fails the tests that this tool applies then that *is* a guarantee that synchronisation via the address will *not* be possible. However, I will of course look carefully into any report from a block producer who believes that they are incorrectly appearing in the errors report.

This report uses conventions adopted by the community of Telos Network Block Producers. Those same conventions can be used to identify an API endpoint to be used as the parameter to the report - see [Telos Network Block Producers](#telos-network-block-producers) below. Note that this makes this tool specific to the Telos Network, unless there are other EOSIO based networks that have adopted the same conventions.

## Telos Network Block Producers

Both the [API Report](#api-report) and [Get Peers](#get-peers) tools rely on conventions adopted by the community of Telos Network Block Producers to advertise the chain services that they provide. The example commands that I have provided for those tools use the Telos UK API endpoint for the Telos testnet.

What if you want to run those tools for the Telos mainnet or using an API endpoint provided by another block producer? You can employ the same conventions that the tools use to gather their information to find a suitable, alternative API endpoint.

Find an EOS block explorer that caters for the chain you're interested in. I'm going to demonstrate using [Bloks.io](https://www.bloks.io/), which is my go-to block explorer for the Telos Network.

1. Lookup the [Telos testnet block producers](https://telos-test.bloks.io/#producers) or [Telos mainnet block producers](https://telos.bloks.io/#producers). For the subsequent steps below I will continue with Telos testnet but the same approach can be used for Telos mainnet.
2. Links are provided for the block producers, which include that block producer's website.
3. Telos block producers publish a `chains.json` file at the `/chains.json` path for their website. To illustrate, here is the content currently returned at [https://telosuk.io/chains.json](https://telosuk.io/chains.json):

```json
{
	"chains": {
		"4667b205c6838ef70ff7988f6e8257e8be0e1284a2f59699054a018f743b1d11": "/bp.json",
		"1eaa0824707c8c16bd25145493bf062aecddfeb56c736f6ba6397f3195f33c9f": "/testnet_bp.json"
	}
}
```

4. This maps chain ids to the website paths for other JSON files that provide details of the services the block producer provides for that chain.
5. We can confirm that the second entry in `chains` above corresponds to Telos testnet by looking up the [Telos testnet chain information on Bloks.io](https://telos-test.bloks.io/chain).
6. So, if we retrieve [https://telosuk.io/testnet_bp.json](https://telosuk.io/testnet_bp.json) as indicated above, the content returned includes a value for an `api_endpoint` as below:

```json
"api_endpoint":	"http://testnet-api.telosuk.io"
```

Using this approach you can determine a Telos mainnet or testnet API endpoint to use for the [API Report](#api-report) or [Get Peers](#get-peers) tool.
